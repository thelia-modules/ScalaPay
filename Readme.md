# Scalapay

This module adds the payment solution Scalapay.

## Installation

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is Scalapay.
* Activate it in your thelia administration panel

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/scalapay-module:~1.0
```

## Usage
* Contact Scalapay to create a Scalapay Partner account https://www.scalapay.com/.
* Connect to Scalapay to retrieve your api key https://partner.scalapay.com/login.
* Go to the module configuration and add your api key.
* Set the operation mode to production

Documentation : https://developers.scalapay.com/docs

If you want to use Scalapay in test mode, you can follow this link to get the test environment information of Scalapay :
https://developers.scalapay.com/docs/test-environments