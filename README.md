net2rent PHPConnector
========================

net2rent offers the PHPConnector, a simple but effective PHP class that will help you making calls to the net2rent API.

PHPConnector includes authentication to the API and basic functions for portals. At this time, the next functions are available:

* Portal
  * getProperties(array $options = array())
  * getProperty($property_id, array $options = array())
  * getCountries()
  * getCities()
  * getPropertyStatus($propertyId)
  * getBlockedPeriods($propertyId)
  * getTypologyDaysPrices($typologyId)
  * getTypologyDaysPricesPeriods($typologyId)

* Publisher
  * getProperties(array $options = array())
  * getProperty($property_id, array $options = array())
  * getCountries()
  * getCities()
  * insertProperty(array $properyOptions)
  * updateProperty(int $propertyId, array $properyOptions)
  * updateImages(int $typologyId, array $images)
  * updateEquipment(int $propertyId, array $equipment)
  * updatePropertyStatus(int $propertyId, array $availability)
  * updateTypologyPrices(int $typologyId, array $prices)


An example of the the class instantiation and the use of these functions is also included into 'examples' folder.

Version
--------------

Current version is 0.2.1

Support or Contact
--------------

Having trouble with PHPConnector or net2rent API? Contact us at soporte@net2rent.com and we’ll help you sort it out.
