# Shopify and Wordpress integration

Simple app to update usermeta table in Wordpress from a Single Page Application form.
## Git

Clone with submodules

    git clone git://repo/path.git path
    cd path
    git submodule update --init --recursive
## Wordpress

### Docker

Initialize the containers (if running outside Docker, ignore this)

    cd wordpress
    docker compose up -d

### Permalinks

Go to `https://wordpress.domain/wp-admin/options-permalink.php` and change "Common Settings" to "Post name"

### Plugin

Install Basic-Auth plugin from the zip file in

    wordpress/plugins/Basic-Auth.zip
## Configuration

Copy configuration.json.dist to configuration.json and edit the environment variables:

    host: full https domain where the web app is hosted
    shopifyApiKey: Shopify API key
    shopifySecret: Shopify API secret
    shopifyScope: Scopes needed, comma separated (https://shopify.dev/api/admin/access-scopes)
    wordpressDbTablesPrefix: Prefix of WordPress database tables (i.e. "wp_")
    wordpressDbHost: Domain or IP of WordPress database
    wordpressDbName: WordPress database name
    wordpressDbUser: WordPress database user
    wordpressDbPassword: WordPress database password
    metaKey: name of the metakey to be saved alongside Shopify shop and accessToken (i.e. "telegram_credentials")
    wordPressAuthEndpointUrl: Basic-Auth plugin endpoint (i.e. "http://wordpress.domain/wp-json/basic-auth/v1/check-auth")

### Install dependencies

First, ensure your PHP version is 7.0 or greater. (`php -v`)

Then install the necessary project dependencies through `composer`:

```
cd src
composer install
```
### Run the application (Apache/Nginx)
```
sudo ln -s /full/path/src/public /var/www/html
```
### Publishing in Shopify

Create a new App pointing "App Url" to `https://domain.com` and "Allowed redirection URL(s)" to `https://domain.com/auth/shopify/callback`