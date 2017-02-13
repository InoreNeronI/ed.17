
/usr/bin/mysqldump -u root -pr00t17 -h localhost ed17probak --no-data > /var/www/probak/app/cache/data-structure.sql
/usr/bin/mysqldump -u root -pr00t17 -h localhost ed17probak --no-create-info --ignore-table=ed17probak.ikasleak > /var/www/probak/app/cache/data.sql
/usr/bin/zip -P r00t17 -j /live/image/data.zip /var/www/probak/app/cache/*.sql
rm -rfv /var/www/probak/app/cache/*.sql