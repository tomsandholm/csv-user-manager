push:
	sudo cp index.php /var/www/html
	sudo cp view.php /var/www/html
	sudo cp users.csv /var/www/html
	sudo cp hosts.csv /var/www/html
	sudo cp hosts-block.txt /var/www/html
	sudo cp users-block.txt /var/www/html
	sudo cp groups-block.txt /var/www/html
	sudo cp update-group-block.yml /var/www/html
	sudo cp update-user-block.yml /var/www/html
	sudo cp setup-local-user-homes.yml /var/www/html
	sudo cp sshd_allow_group.yml /var/www/html
	sudo cp sshd_unallow_group.yml /var/www/html
	sudo chown www-data:www-data /var/www/html/index.php /var/www/html/view.php /var/www/html/users.csv /var/www/html/hosts.csv /var/www/html/hosts-block.txt /var/www/html/users-block.txt /var/www/html/groups-block.txt /var/www/html/update-group-block.yml /var/www/html/update-user-block.yml /var/www/html/setup-local-user-homes.yml /var/www/html/sshd_allow_group.yml /var/www/html/sshd_unallow_group.yml
