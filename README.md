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
	use AmazonMWS\Product\AmazonProductSearch;

    $amz = new AmazonProductSearch([
		'keyId' => 'XXXXXX', // Required - Developer Key
		'secretKey' => 'XXXXXX', // Required - Developer Secret
		'serviceUrl' => 'https://mws.amazonservices.com/', // Optional - Defaults to US
		'debug' => true // Optional - Defaults to false
	]);
	$amz->setMerchantId('XXXXXX'); // Required - Seller/Merchant ID
	
	// Required(Sometimes) - When Dealing With Products
	// http://docs.developer.amazonservices.com/en_US/dev_guide/DG_Endpoints.html
	$amz->setMarketplaceId('XXXXXX');
	
	// Required(Sometimes) - If Making A Request For Another Seller (They delegated rights)
	// https://images-na.ssl-images-amazon.com/images/G/02/mwsportal/doc/en_US/bde/MWSAuthToken.pdf
	$amz->setMWSAuthToken('XXXXXXXXX');
	
	$amz->setQuery('apple');	
	$amz->searchProducts();
	
    $result = $amz->getProduct();
```
This example shows a function used to send a previously-created XML feed to Amazon to update Inventory numbers:
```php
	use AmazonMWS\Feed\AmazonFeed;
	
    $amz=new AmazonFeed([
		'keyId' => 'XXXXXX', // Required - Developer Key
		'secretKey' => 'XXXXXX', // Required - Developer Secret
		'serviceUrl' => 'https://mws.amazonservices.com/', // Optional - Defaults to US
		'debug' => true // Optional - Defaults to false
	]);
	$amz->setMerchantId('XXXXXXX'); // Required - Seller/Merchant ID
	
	// Required(Sometimes) - If Making A Request For Another Seller (They delegated rights)
	// https://images-na.ssl-images-amazon.com/images/G/02/mwsportal/doc/en_US/bde/MWSAuthToken.pdf
	$amz->setMWSAuthToken('XXXXXX');
	
    $amz->setFeedType("_POST_INVENTORY_AVAILABILITY_DATA_"); //feed types listed in documentation
    $amz->setFeedContent($feed);
    $amz->submitFeed();
    $result = $amz->getResponse();
```
