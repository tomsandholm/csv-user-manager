#!/usr/bin/env bash

set -u

CSV_FILE="hosts.csv"
MODE="report"

usage() {
    cat <<'USAGE'
Usage: ./check-remote-groups.sh [--apply] [hosts.csv]

Checks each machine-group in hosts.csv over SSH. The first column is both
the SSH target and the remote group name. The script reports whether the group exists
in /etc/group and whether each configured member is present.
--apply appends only missing users to existing remote groups.
USAGE
}

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    usage
    exit 0
fi

if [[ "${1:-}" == "--apply" ]]; then
    MODE="apply"
    shift
fi

if [[ $# -gt 1 ]]; then
    usage >&2
    exit 2
fi

if [[ $# -eq 1 ]]; then
    CSV_FILE="$1"
fi

if [[ ! -r "$CSV_FILE" ]]; then
    echo "Cannot read hosts CSV: $CSV_FILE" >&2
    exit 1
fi

read -r header < "$CSV_FILE"
if [[ "$header" != "machine-group,group-id,member-list" ]]; then
    echo "Expected hosts.csv header: machine-group,group-id,member-list" >&2
    exit 1
fi

remote_check='
set -euo pipefail

machine_group=$1
group_name=$2
expected_members=$3
mode=$4

echo "REMOTE_HOST=$machine_group"
echo "GROUP_NAME=$group_name"

group_entry=$(awk -F: -v group="$group_name" "\$1 == group { print; exit }" /etc/group)
if [[ -z "$group_entry" ]]; then
    echo "GROUP_FOUND=no"
    echo "GROUP_RESULT=not-found"
    echo "APPEND_RESULT=skipped-group-not-found"
    exit 0
fi

echo "GROUP_FOUND=yes"
IFS=: read -r actual_name actual_gid actual_members <<< "$group_entry"
echo "GROUP_GID=$actual_gid"
echo "REMOTE_MEMBERS=${actual_members:-<none>}"

if [[ -z "$expected_members" ]]; then
    echo "EXPECTED_MEMBERS=<none>"
    echo "MEMBER_RESULT=none-configured"
    exit 0
fi

echo "EXPECTED_MEMBERS=$expected_members"
member_result=present
missing_members=()
IFS=, read -ra expected_array <<< "$expected_members"
for member in "${expected_array[@]}"; do
    member="${member#"${member%%[![:space:]]*}"}"
    member="${member%"${member##*[![:space:]]}"}"
    if [[ -z "$member" ]]; then
        continue
    fi
    if [[ ",$actual_members," == *",$member,"* ]]; then
        echo "MEMBER user=$member present=yes"
    else
        echo "MEMBER user=$member present=no"
        member_result=missing
        missing_members+=("$member")
    fi
done
echo "MEMBER_RESULT=$member_result"

if [[ "${#missing_members[@]}" -eq 0 ]]; then
    echo "APPEND_RESULT=none-needed"
elif [[ "$mode" == "apply" ]]; then
    for member in "${missing_members[@]}"; do
        echo "APPENDING user=$member group=$group_name"
        sudo gpasswd --add "$member" "$group_name"
        updated_entry=$(getent group "$group_name" || true)
        updated_members="${updated_entry##*:}"
        if [[ ",$updated_members," != *",$member,"* ]]; then
            echo "APPEND_VERIFY user=$member present=no"
            exit 1
        fi
        echo "APPEND_VERIFY user=$member present=yes"
    done
    echo "APPEND_RESULT=completed"
else
    echo "APPEND_RESULT=report-only"
fi
'

processed=0
failed=0

while IFS= read -r csv_line; do
    [[ "$csv_line" == "$header" || -z "$csv_line" ]] && continue

    if [[ "$csv_line" =~ ^([^,]+),([^,]+),(.*)$ ]]; then
        machine_group="${BASH_REMATCH[1]}"
        member_list="${BASH_REMATCH[3]}"
    else
        echo "Skipping malformed CSV row: $csv_line" >&2
        ((failed += 1))
        continue
    fi

    member_list="${member_list#\"}"
    member_list="${member_list%\"}"
    group_name="$machine_group"

    echo
    echo "[$((processed + 1))] Checking machine-group: $machine_group"
    echo "  SSH target: $machine_group"
    echo "  Derived group: $group_name"
    echo "  Expected members: ${member_list:-<none>}"
    echo "  Mode: $MODE"

    if ssh -- "$machine_group" bash -s -- "$machine_group" "$group_name" "$member_list" "$MODE" <<< "$remote_check"; then
        ((processed += 1))
    else
        echo "  SSH_RESULT=failed"
        ((failed += 1))
    fi
done < "$CSV_FILE"

echo
echo "Check complete"
echo "  Hosts checked: $processed"
echo "  Failures: $failed"

[[ "$failed" -eq 0 ]]
