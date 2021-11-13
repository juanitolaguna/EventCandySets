# Shopware Set Plugin

- Each product can be composed aut of sub products
- Stock is recalculated based on the min(...subproducts) value
- Availability can change dynamicly based on what is inside the Cart: id products share subitems, stock is recalculated at dynamically


## Running the tests (Dockware Dev)

- modify the vendor/shopware/core/TestBootstrap.php
``` 
$bootstrapper = (new TestBootstrapper())
    ->addActivePlugins('EventCandySets')
    ->bootstrap();
```

On error `Unable to read key from file /var/www/html/var/test/jwt/private.pem`

-`mkdir -p /var/www/html/var/test/jwt/ && cp /var/www/html/config/jwt/private.pem /var/www/html/var/test/jwt/private.pem`