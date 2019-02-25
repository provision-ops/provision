#
# Install Aegir debian packages located in the projects stable repository.
#
# This script is tuned for Debian 9 - Stretch
#


sudo apt-get install --yes wget apt-transport-https

sudo wget -O /usr/share/keyrings/aegir-archive-keyring.gpg https://debian.aegirproject.org/aegir-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/aegir-archive-keyring.gpg] https://debian.aegirproject.org stable main" | sudo tee -a /etc/apt/sources.list.d/aegir-stable.list
sudo apt-get update
echo "debconf debconf/frontend select Noninteractive" | sudo debconf-set-selections


sudo apt-get install --yes mariadb-server-10.1
sudo /usr/bin/mysql -e "GRANT ALL ON *.* TO 'aegir_root'@'localhost' IDENTIFIED BY 'PASSWORD' WITH GRANT OPTION"


sudo debconf-set-selections <<EOF
aegir3-hostmaster aegir/db_password string PASSWORD
aegir3-hostmaster aegir/db_password seen  true
aegir3-hostmaster aegir/db_user string aegir_root
aegir3-hostmaster aegir/db_host string localhost
aegir3-hostmaster aegir/email string  aegir@example.com
aegir3-hostmaster aegir/site  string  aegir.example.com
postfix postfix/main_mailer_type select Local only

EOF

sudo DPKG_DEBUG=developer apt-get install --yes aegir3
