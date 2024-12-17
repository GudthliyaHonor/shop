# Pre-install

## Step 1
Install composer, see https://getcomposer.org/

## Step 2
Run the following command to install the required libraries.
```
> composer install
```
If composer.json is updated, you should run the following the command to update dependent libraries:
```
> composer update
```
If `.env` is not created, you can run composer scripts manually:
```
composer run-script post-install-cmd
```

## Install required PHP extensions
Redis, Mongodb
You can find these extensions in http://pecl.php.net/.

