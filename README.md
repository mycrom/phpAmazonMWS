phpAmazonMWS - 3.0 - A New Hope
================================

A library to connect to Amazon's Merchant Web Services (MWS) in an object-oriented manner, with a focus on intuitive usage.  

This is __NOT__ for Amazon Web Services (AWS) - Cloud Computing Services.

NOTICE: This version has been updated to play nice with frameworks such as loading in config, autoloading, and using log services

## Example Usage
Here are a couple of examples of the library in use.
All of the technical details required by the API are handled behind the scenes,
so users can easily build code for sending requests to Amazon
without having to jump hurdles such as parameter URL formatting and token management. 

Here is an example of a function used to get a list of products from Amazon:
```php
function getAmazonProducts() {
    $amz = new \AmazonMWS\Product\AmazonProductSearch([
		'merchantId' => 'XXX',
		'marketplaceId' => 'XXX',
		'keyId' => 'XXX',
		'secretKey' => 'XXX',
		'serviceUrl' => 'https://mws.amazonservices.com/',
		'debug' => true
	]);
	$amz->setQuery('apple');	
	$amz->searchProducts();
    return $amz->getProduct();
}
```
This example shows a function used to send a previously-created XML feed to Amazon to update Inventory numbers:
```php
function sendInventoryFeed($feed) {
    $amz=new \AmazonMWS\Feed\AmazonFeed([
		'merchantId' => 'XXX',
		'keyId' => 'XXX',
		'secretKey' => 'XXX',
		'serviceUrl' => 'https://mws.amazonservices.com/',
		'debug' => true
	]);
    $amz->setFeedType("_POST_INVENTORY_AVAILABILITY_DATA_"); //feed types listed in documentation
    $amz->setFeedContent($feed);
    $amz->submitFeed();
    return $amz->getResponse();
}
```
