<?php
namespace Net2rent\Connector;

class Portal extends AbstractConnector
{

    protected $_endPoints = array(
        'properties_availables' => '/portals/{{portal}}/typologies_availability',
        'properties' => '/portals/{{portal}}/typologies',
        'property_available' => '/portals/{{portal}}/typologies_availability/%s',
        'property' => '/portals/{{portal}}/typologies/%s',
        'typology_properties' => '/typologies/%s/properties',
        'companies' => '/portals/{{portal}}/companies',
        'property_status' => '/properties/%s/propertystatus',
        'typology_prices' => '/typologies/%s/pricecalendar',
    );

    public function getCompanies()
    {
        $endPoint = $this->getEndPoint('companies');
        $companies = $this->api($endPoint);
        // $companiesTotal = $this->api($endPoint . '/size');

        return array(
            // 'total' => $companiesTotal['total'],
            'items' => $companies
        );
    }

    /**
     * Get typology properties
     *
     * @param  integer  $typology_id Typology id
     * @return array  (items|total)
     */
    public function getTypologyProperties($typology_id)
    {
        $endPoint = $this->getEndPoint('typology_properties', array($typology_id));

        $apiProperties = $this->api($endPoint);
        $apiPropertiesTotal = $this->api($endPoint . '/size');

        $properties = array();
        foreach ($apiProperties as $apiProperty) {
            $equipment_array = $this->api("/typologies/" . $apiProperty['typology_id'] . "/equipment");
            $equipments = array();
            $not_equipments = array(
                'id',
                'typology_id',
                'creation_date',
                'creation_usr',
                'edition_date',
                'edition_usr'
            );
            foreach ($equipment_array as $equipment_name => $equipment_value) {
                if (strpos($equipment_name, '_model') == false && in_array($equipment_name, $not_equipments, true) == false) {
                    $equipments[$equipment_name] = $equipment_value;
                }
            }

            $properties[] = $apiProperty + array(
                'id' => $apiProperty['id'],
                'name' => $apiProperty['name'],
                'ref' => $apiProperty['ref_property_string'],
                'capacity' => $apiProperty['typology_capacity'],
                'type' => $apiProperty['typology_building_type'],
                'description' => array(
                    'es' => strip_tags($apiProperty['typology_description_es']) ,
                    'ca' => strip_tags($apiProperty['typology_description_ca']) ,
                    'en' => strip_tags($apiProperty['typology_description_en']) ,
                    'fr' => strip_tags($apiProperty['typology_description_fr']) ,
                    'de' => strip_tags($apiProperty['typology_description_de']) ,
                    'nl' => strip_tags($apiProperty['typology_description_nl']) ,
                    'it' => strip_tags($apiProperty['typology_description_it']) ,
                    'ru' => strip_tags($apiProperty['typology_description_ru'])
                ) ,
                'address' => array(
                    'address' => $apiProperty['typology_building_address'],
                    'urbanization' => $apiProperty['typology_building_urbanization'],
                    'zipcode' => $apiProperty['typology_building_cp'],
                    'zone' => $apiProperty['typology_building_zone'],
                    'country' => $apiProperty['typology_building_country_iso'],
                    'province' => $apiProperty['typology_building_province'],
                    'city' => $apiProperty['typology_building_city'],
                    'latitude' => $apiProperty['typology_building_lat'],
                    'longitude' => $apiProperty['typology_building_long']
                ) ,
                'equipment' => $equipments,
                'average_evaluation' => $apiProperty['typology_average_evaluation'],
                'room_number' => $apiProperty['typology_room_number'],
                'price' => $apiProperty['typology_rent_price'],
                'price_offer' => $apiProperty['typology_rent_price_offer'],
                'online_reservation' => $apiProperty['typology_property_online_reservation'],
                'image' => (isset($apiProperty['typology_image_id'])) ? sprintf('%s/typologies/%s/images/%s/image', $this->apiBaseUrl, $apiProperty['typology_id'], $apiProperty['typology_image_id']) : null,
                'area' => $apiProperty['property_meters'],
                'toilets' => $apiProperty['typology_toilets'],
            );
        }

        return array(
            'total' => $apiPropertiesTotal['size'],
            'items' => $properties
        );
    }

    public function getPropertyStatus($propertyId)
    {
        $endPoint = $this->getEndPoint('property_status', array($propertyId));
        return $this->api($endPoint);
    }

    public function getBlockedPeriods($propertyId)
    {
        $propertyStatus = $this->getPropertyStatus($propertyId);
        $blockedPeriods = array();
        $initialBlockedDay = null;
        $endBlockedDay = null;
        foreach($propertyStatus as $propertyStatusDay) {
            if($propertyStatusDay['status'] != 'available') {
                if(!$initialBlockedDay) {
                    $initialBlockedDay = $propertyStatusDay['day'];
                }
                $endBlockedDay = $propertyStatusDay['day'];
            }
            else {
                if($initialBlockedDay) {
                    $blockedPeriods[] = array(
                        'initial_date' => $initialBlockedDay,
                        'end_date' => $endBlockedDay,
                    );
                    $initialBlockedDay = null;
                    $endBlockedDay = null;
                }
            }
        }
        if($initialBlockedDay) {
            $blockedPeriods[] = array(
                'initial_date' => $initialBlockedDay,
                'end_date' => $endBlockedDay,
            );
        }
        return $blockedPeriods;
    }

    public function getTypologyDaysPrices($typologyId)
    {
        $endPoint = $this->getEndPoint('typology_prices', array($typologyId));
        return $this->api($endPoint);
    }

    public function getTypologyDaysPricesPeriods($typologyId)
    {
        $typologyDaysPrices = $this->getTypologyDaysPrices($typologyId);
        $pricesPeriods = array();
        $initialPriceDay = null;
        $endPriceDay = null;
        $price = null;
        $minimumStay = null;

        foreach($typologyDaysPrices as $typologyDayPrice) {
            $rentPrice = (isset($typologyDayPrice['rentprice'])) ? $typologyDayPrice['rentprice'] : 0;
            $discountPrice = (isset($typologyDayPrice['discountprice'])) ? $typologyDayPrice['discountprice'] : 0;
            $newPrice = $rentPrice - $discountPrice;
            $newMinimumStay = (isset($typologyDayPrice['minimum_nights'])) ? $typologyDayPrice['minimum_nights'] : 1;

            $isDifferent = (bool)(($price !== $newPrice) || ($minimumStay !== $newMinimumStay));

            if($isDifferent) {
                if($price) {
                    $pricesPeriods[] = array(
                        'price' => $price,
                        'minimum_stay' => $minimumStay,
                        'start_date' => $initialPriceDay,
                        'end_date' => $endPriceDay,
                    );
                }
                $initialPriceDay = $typologyDayPrice['day'];
                $price = $newPrice;
                $minimumStay = $newMinimumStay;
            }
            $endPriceDay = $typologyDayPrice['day'];
        }
        if($price) {
            $pricesPeriods[] = array(
                'price' => $price,
                'minimum_stay' => $minimumStay,
                'start_date' => $initialPriceDay,
                'end_date' => $endPriceDay,
            );
        }
        return $pricesPeriods;
    }
}
