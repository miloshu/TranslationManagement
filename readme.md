## 目录权限
~~~
chmod -R 755 storage

chmod -R 755 bootstrap/cache

~~~
## laravel依赖
~~~
composer install

composer update

php artisan key:generate 

~~~

## Database & Seeding
~~~
php artisan migrate

php artisan db:seed
~~~
