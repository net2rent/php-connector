<?php
namespace Net2rent\Connector;

class Portal extends AbstractConnector
{

    protected $_endPoints = array(
        'cities' => '/portals/{{portal}}/properties-cities',
        'properties_availables' => '/portals/{{portal}}/typologies_availability',
        'properties' => '/portals/{{portal}}/typologies',
        'property_available' => '/portals/{{portal}}/typologies_availability/%s',
        'property' => '/typologies/%s',
        'typology_properties' => '/typologies/%s/properties',
        'companies' => '/portals/{{portal}}/companies',
        'property_status' => '/properties/%s/propertystatus',
        'availability_portals' => '/typologies/%s/portal/{{portal_id}}/availability_portals',
        'availability_property' => '/typologies/%s/availability',
        'typology_prices' => '/typologies/%s/pricecalendar',
        'contacts' => '/portals/{{portal}}/contacts',
        'contacts_modify' => '/contacts/%s',
        'booking' => '/portals/{{portal}}/bookings',
        'booking_external' => '/bookings/booking/%s',
        'booking_external_ref_id' => '/bookings/booking/%s/%s',
        'booking_modify' => '/bookings/%s',
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
     * @param  boolean  $options['simple_response'] To get few fields
     * @param  integer  $options['limit'] Limit to this number of properties 
     * @return array  (items|total)
     */
    public function getTypologyProperties($typology_id, $options = array())
    {
        $params = array();
        if (isset($options['simple_response'])) {
            $params['simple_response'] = ($options['simple_response']) ? '1' : '0';
        }
        if (isset($options['limit'])) {
            $limit = $options['limit'] ? (int)$options['limit'] : null;
        }
        $endPoint = $this->getEndPoint('typology_properties', array($typology_id));

        $apiProperties = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
//        $apiPropertiesTotal = $this->api($endPoint . '/size');

        $properties = array();
        $num=0;
        $total=0;
        foreach ($apiProperties as $apiProperty) {
            $num++;
            if(isset($limit) && $num>$limit) { 
                $total=$num-1;
                break; 
            } else {
                $total++;
            }
            
            if(isset($options['simple_response']) && $options['simple_response']) {
                $properties[] = $apiProperty + array(
                    'id' => $apiProperty['id'],
                    'name' => $apiProperty['name'],
                    'ref' => $apiProperty['ref_property_string']
                );
            }
            else {
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
                    'image' => (isset($apiProperty['typology_image_id'])) ? sprintf('%s/typologies/%s/images/%s/image.jpg', $this->apiBaseUrl, $apiProperty['typology_id'], $apiProperty['typology_image_id']) : null,
                    'area' => $apiProperty['property_meters'],
                    'toilets' => $apiProperty['typology_toilets'],
                );
            }
        }

        return array(
            'total' => $total,
            'items' => $properties
        );
    }

    public function getPropertyStatus($propertyId)
    {
        $endPoint = $this->getEndPoint('property_status', array($propertyId));
        return $this->api($endPoint);
    }
    
    /**
     * Get availability days. If dates not set, gets all future days from today
     *
     * @param type $typologyId
     * @param  date  $options['from'] Format YYYY-MM-DD. Optional
     * @param  date  $options['to'] Format YYYY-MM-DD. Optional
  
     * @return array array of days with number of available properties for each day
     */
    public function getAvailabilityDays($typologyId, array $options)
    {
        $params = array();
        $params['from']=date('Y-m-d');
        $params['to']=date('Y-m-d',strtotime($params['from'].' + 1 year'));
        if(isset($options['from'])) {
            $params['from'] = $options['from'] ? $options['from']  : date('Y-m-d');
        }
        if(isset($options['to'])) {
            $params['to'] = $options['to'] ? $options['to']  : date('Y-m-d',strtotime($params['from'].' + 1 year'));
        }
        
        $endPoint1 = $this->getEndPoint('availability_portals', array($typologyId));
        $availabilityPortalDays = $this->api(sprintf($endPoint1 . '?%s', http_build_query($params)));
        
        $endPoint2 = $this->getEndPoint('typology_prices', array($typologyId));
    
        $availabilityPropertyDays = $this->api(sprintf($endPoint2 . '?%s', http_build_query($params)));
        $availabilityPropertyDaysDayIndexed=array();
        foreach($availabilityPropertyDays as $availabilityPropertyDay) {
            $availabilityPropertyDaysDayIndexed[$availabilityPropertyDay['day']]=$availabilityPropertyDay;
        }

        $return=array();
        $i=0;
        foreach($availabilityPortalDays as $availabilityPortalDay) {
            // if matches availability portal day with availability day, get data, else put available to 0 
            $availabilityPropertyDay=isset($availabilityPropertyDaysDayIndexed[$availabilityPortalDay['day']]) && $availabilityPropertyDaysDayIndexed[$availabilityPortalDay['day']]['day']==$availabilityPortalDay['day'] ? $availabilityPropertyDaysDayIndexed[$availabilityPortalDay['day']] : array('day'=>$availabilityPortalDay['day'],'available'=>0);
            $available=(int)$availabilityPortalDay['available']-(int)$availabilityPortalDay['bookings'];
            if($available>(int)$availabilityPropertyDay['available']) {
                $available=(int)$availabilityPropertyDay['available'];
            }
            if($available<0) { $available=0; }
            $return[]=array(
                'day' => $availabilityPortalDay['day'],
                'entry_days' => $availabilityPropertyDay['entry_days'],
                'out_days' => $availabilityPropertyDay['out_days'],
                'minimum_nights' => $availabilityPropertyDay['minimum_nights'],
                'rentprice' => $availabilityPropertyDay['rentprice'],
                'available' => $available
            );
            $i++;
        }
        return $return;
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
        $norefoundprice = null;
        $minimumStay = null;

        foreach($typologyDaysPrices as $typologyDayPrice) {
            $rentPrice = (isset($typologyDayPrice['rentprice'])) ? $typologyDayPrice['rentprice'] : 0;
            $discountPrice = (isset($typologyDayPrice['discountprice'])) ? $typologyDayPrice['discountprice'] : 0;
            $norefoundRentPrice  = (isset($typologyDayPrice['norefoundprice'])) ? $typologyDayPrice['norefoundprice'] : 0;
            $norefoundDiscountPrice  = (isset($typologyDayPrice['norefounddiscountprice'])) ? $typologyDayPrice['norefounddiscountprice'] : 0;
            $newPrice = $rentPrice - $discountPrice;
            $newNoRefoundPrice = $norefoundRentPrice - $norefoundDiscountPrice;
            $newMinimumStay = (isset($typologyDayPrice['minimum_nights'])) ? $typologyDayPrice['minimum_nights'] : 1;

            $isDifferent = (bool)(($price !== $newPrice) || ($minimumStay !== $newMinimumStay) || ($norefoundprice !== $newNoRefoundPrice));

            if($isDifferent) {
                if($price) {
                    $pricesPeriods[] = array(
                        'price' => $price,
                        'norefoundprice' => $norefoundprice,
                        'minimum_stay' => $minimumStay,
                        'start_date' => $initialPriceDay,
                        'end_date' => $endPriceDay,
                    );
                }
                $initialPriceDay = $typologyDayPrice['day'];
                $price = $newPrice;
                $norefoundprice = $newNoRefoundPrice;
                $minimumStay = $newMinimumStay;
            }
            $endPriceDay = $typologyDayPrice['day'];
        }
        if($price) {
            $pricesPeriods[] = array(
                'price' => $price,
                'norefoundprice' => $norefoundprice,
                'minimum_stay' => $minimumStay,
                'start_date' => $initialPriceDay,
                'end_date' => $endPriceDay,
            );
        }
        return $pricesPeriods;
    }

    /**
     * get portal contacts
     * @param  integer $params['company_id'] Company unique id to filter results
     * @param  integer $params['typology_id'] Typology unique id to filter results
     * @param  integer $params['property_id'] Property unique id to filter results
     * @param  string $params['email'] Email to filter results
     * @param  string $params['name'] Name to filter results
     * @param  string $params['surname'] Surname to filter results
     * @return array    Contacts
     */
    public function getContacts($params = array())
    {
        $endPoint = $this->getEndPoint('contacts');
        return $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
    }

    /**
     * insert contact
     * @return object    Contact
     */
    public function insertContact($params = array())
    {
        $endPoint = $this->getEndPoint('contacts');
        return $this->api($endPoint.'/', 'POST', $params);
    }
    
    /**
     * modify contact
     * @return object    Contact
     * @param  integer $contactId Contact id
     */
    public function modifyContact($contactId,$params = array())
    {
        $endPoint = $this->getEndPoint('contacts_modify');
        return $this->api(sprintf($endPoint, $contactId), 'PUT', $params);
    }

    /**
     * get booking by external id of the portal
     * @param  string $external_id Booking external id
     * @param  string $options['status'] Booking status. Filter booking only if is one of the submitted status, can be multiple separated by comma (,). Values [prebooked,booked,cancelled]
     * @return array
     */
    public function getBookingByExternalId($external_id,$options = array())
    {
        $params = array();
        if(isset($options['status'])) {
            $params['status'] = $options['status'] ? $options['status']  : "";
        }
        
        $endPoint = $this->getEndPoint('booking_external', array($external_id));
        return $this->api(sprintf($endPoint . '?%s',http_build_query($params)));
    }
    
    /**
     * get booking by external ref and external id of the portal
     * @param  string $external_ref Booking external id
     * @param  string $external_id Booking external id
     * @param  string $options['status'] Booking status. Filter booking only if is one of the submitted status, can be multiple separated by comma (,). Values [prebooked,booked,cancelled]
     * @return array 
     */
    public function getBookingByExternalRefExternalId($external_ref,$external_id,$options = array())
    {
        $params = array();
        if(isset($options['status'])) {
            $params['status'] = $options['status'] ? $options['status']  : "";
        }
        
        $endPoint = $this->getEndPoint('booking_external_ref_id', array($external_ref,$external_id));
        return $this->api(sprintf($endPoint . '?%s',http_build_query($params)));
    }    
    
    public function insertBooking($params = array())
    {
        $endPoint = $this->getEndPoint('booking');
        return $this->api($endPoint.'/', 'POST', $params);
    }
    
    /**
     * modify booking
     * to modify dates, set params "date_in" and "date_out"
     * to cancel booking, set param status=cancelled
     * @param  integer $bookingId Booking id
     * @p
     */ 
    public function modifyBooking($bookingId,$params = array())
    {
        $endPoint = $this->getEndPoint('booking_modify');
        return $this->api(sprintf($endPoint, $bookingId), 'PUT', $params);
    }
}
