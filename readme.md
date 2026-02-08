# Shopware Set Plugin

**This is a dead project, so i made it opensource for educational means.**

This project is outdated and no longer in use. Developers often borrow code from each other, and this is fine. That's why I decided to open source it.

The most interesting challenge was to dynamically calculate the available stock for composed products in the shopping cart to prevent users from inadvertently depleting their own cart when adding another composed product with the same subproducts. To achieve acceptable performance, the calculations were moved entirely to the database. If I were to tackle this today, I might approach it differently, but here it is.

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
