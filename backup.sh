#s3 backup

# Uncomment following line to use static domains list
# DOMAINS="webspace1.com webspace2.com"

# Uncomment following line to use dynamic domains list from Plesk
DOMAINS=$(mysql -uadmin -p`cat /etc/psa/.psa.shadow` psa -Ns -e "SELECT d.name FROM Subscriptions AS s LEFT JOIN domains AS d ON s.object_id = d.id")

php s3backups.php $DOMAINS > ./backups/script.sh
sh ./backups/script.sh