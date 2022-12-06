# OFS 2.0 Back End

## Framework 
* Laravel 8

## Php version
* ^7.3|^8.0

## Dependencies Installation
```bash
cd /"{ofs 2.0 project root folder}"
$ composer install
$ cp .env.example .env
$ php artisan key:generate
$ php artisan passport:install
```

## Config .env
### Example Database Connection
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=homestead
DB_USERNAME=homestead
DB_PASSWORD=secret

# UMS DATABASE - User Management Database
DB_UMS_HOST=192.168.56.14
DB_UMS_PORT=3306
DB_UMS_DATABASE=csi_mcd
DB_UMS_USERNAME=root
DB_UMS_PASSWORD=password

# OFS_0_1_2
DB_OFS_HOST=192.168.56.14
DB_OFS_PORT=3306
DB_OFS_DATABASE=ofs
DB_OFS_USERNAME=root
DB_OFS_PASSWORD=password

# OFS_READ
DB_OFS_READ=192.168.56.14
DB_OFS_PORT_READ=3306
DB_OFS_DATABASE_READ=ofs
DB_OFS_USERNAME_READ=root
DB_OFS_PASSWORD_READ=password

# OFS_WRITE
DB_OFS_WRITE=192.168.56.14
DB_OFS_PORT_WRITE=3306
DB_OFS_DATABASE_WRITE=ofs
DB_OFS_USERNAME_WRITE=root
DB_OFS_PASSWORD_WRITE=password
```