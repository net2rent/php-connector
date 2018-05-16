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
        'company' => '/companies/%s',
        'property_status' => '/properties/%s/propertystatus',
        'property_accessories' => '/properties/%s/propertyaccessories',
        'availability_portals' => '/typologies/%s/portal/{{portal_id}}/availability_portals',
        'availability_property' => '/typologies/%s/availability',
        'typology_prices' => '/typologies/%s/pricecalendar',
        'typology_portal' => '/typologies/%s/portals/{{portal_id}}',
        'contacts' => '/portals/{{portal}}/contacts',
		'contact' => '/contacts/',
        'contacts_modify' => '/contacts/%s',
        'booking' => '/portals/{{portal}}/bookings',
        'bookings_external' => '/bookings/bookings/%s',
        'booking_external' => '/bookings/booking/%s',
        'booking_external_ref_id' => '/bookings/booking/%s/%s',
        'booking_modify' => '/bookings/%s',
		'booking_card' => '/bookings/%s/cards/',
		'bookingrequest' => '/bookings/bookingrequests',
		'bookingrequest_modify' => '/bookings/bookingrequests/%s',
		'bookingrequest_external_ref_id' => '/bookings/bookingrequests/%s/%s',
		'property_volumediscounts' => '/typologies/%s/volumediscounts',
		'property_minimumnightspay' => '/typologies/%s/minimumnightspay',
		'property_puntualoffers' => '/typologies/%s/puntualoffers',
		'season_days' => '/seasons/%s/days'
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
    
    public function getCompany($companyId)
    {
        $endPoint = $this->getEndPoint('company', array($companyId));
        $company = $this->api($endPoint);

        return $company;
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
		$availabilityPortalDaysDayIndexed=array();
        foreach($availabilityPortalDays as $availabilityPortalDay) {
            $availabilityPortalDaysDayIndexed[$availabilityPortalDay['day']]=$availabilityPortalDay;
        }
        
        $endPoint2 = $this->getEndPoint('typology_prices', array($typologyId));
    
        $availabilityPropertyDays = $this->api(sprintf($endPoint2 . '?%s', http_build_query($params)));
        $availabilityPropertyDaysDayIndexed=array();
        foreach($availabilityPropertyDays as $availabilityPropertyDay) {
            $availabilityPropertyDaysDayIndexed[$availabilityPropertyDay['day']]=$availabilityPropertyDay;
        }

        $return=array();
        $i=0;
		$date=$params['from'];
		while($date<=$params['to']) {
            // if matches availability portal day with availability day, get data, else put available to 0 
			$availabilityPortalDay=isset($availabilityPortalDaysDayIndexed[$date]) && $availabilityPortalDaysDayIndexed[$date]['day']==$date ? $availabilityPortalDaysDayIndexed[$date] : array('day'=>$date,'available'=>0,'bookings'=>0);
            $availabilityPropertyDay=isset($availabilityPropertyDaysDayIndexed[$date]) && $availabilityPropertyDaysDayIndexed[$date]['day']==$date ? $availabilityPropertyDaysDayIndexed[$date] : array('day'=>$date,'available'=>0);
            $available=(int)$availabilityPortalDay['available']-(int)$availabilityPortalDay['bookings'];
            if($available>(int)$availabilityPropertyDay['available']) {
                $available=(int)$availabilityPropertyDay['available'];
            }
            if($available<0) { $available=0; }
            $return[]=array(
                'day' => $availabilityPortalDay['day'],
                'checkin' => isset($availabilityPropertyDay['checkin']) && $availabilityPropertyDay['checkin'] ? $availabilityPropertyDay['checkin'] : 0,
                'checkout' => isset($availabilityPropertyDay['checkout']) && $availabilityPropertyDay['checkout'] ? $availabilityPropertyDay['checkout'] : 0,
                'release' => isset($availabilityPropertyDay['release']) && $availabilityPropertyDay['release'] ? $availabilityPropertyDay['release'] : 0,
                'minimum_nights' => isset($availabilityPropertyDay['minimum_nights']) && $availabilityPropertyDay['minimum_nights'] ? $availabilityPropertyDay['minimum_nights'] : 0,
                'rentprice' => isset($availabilityPropertyDay['rentprice']) ? $availabilityPropertyDay['rentprice'] : 0,
                'available' => $available
            );
            $i++;
			$date=date('Y-m-d',strtotime($date.' + 1 day'));
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

    public function getTypologyDaysPrices($typologyId,array $options=array())
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
		
		$endPoint = $this->getEndPoint('typology_prices', array($typologyId));
        $typologyDaysPrices = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
		
        return $typologyDaysPrices;
    }

	/**
     * Get prices and minimum_stay periods.
     *
     * @param int $typologyId
     * @param bool $onlyPrices. Optional. If true only aggrupates by prices
  
     * @return array array of prices and minimum_stay periods
     */
    public function getTypologyDaysPricesPeriods($typologyId,$onlyPrices=false)
    {
        $typologyDaysPrices = $this->getTypologyDaysPrices($typologyId);
        $pricesPeriods = array();
        $initialPriceDay = null;
        $endPriceDay = null;
        $price = null;
		$price_no_accessories = null;
        $norefoundprice = null;
		$norefoundprice_no_accessories = null;
		$discount=null;
		$extrapersonprice = null;
        $minimumStay = null;
        $checkin = null;
        $checkout = null;
		
        foreach($typologyDaysPrices as $typologyDayPrice) {
            $rentPrice = (isset($typologyDayPrice['rentprice'])) ? $typologyDayPrice['rentprice'] : 0;
			$rentPriceNoAccessories = (isset($typologyDayPrice['rentprice_no_accessories'])) ? $typologyDayPrice['rentprice_no_accessories'] : $rentPrice;
            $discountPrice = (isset($typologyDayPrice['discountprice'])) ? $typologyDayPrice['discountprice'] : 0;
            $norefoundRentPrice  = (isset($typologyDayPrice['norefoundprice'])) ? $typologyDayPrice['norefoundprice'] : 0;
			$norefoundRentPriceNoAccessories = (isset($typologyDayPrice['norefoundprice_no_accessories'])) ? $typologyDayPrice['norefoundprice_no_accessories'] : $norefoundRentPrice;
            $norefoundDiscountPrice  = (isset($typologyDayPrice['norefounddiscountprice'])) ? $typologyDayPrice['norefounddiscountprice'] : 0;
			$newExtraPersonPrice = (isset($typologyDayPrice['extrapersonprice'])) ? $typologyDayPrice['extrapersonprice'] : 0;
            $newPrice = $rentPrice - $discountPrice;
			$newPriceNoAccessories = $rentPriceNoAccessories;
            $newNoRefoundPrice = $norefoundRentPrice - $norefoundDiscountPrice;
			$newNoRefoundPriceNoAccessories = $norefoundRentPriceNoAccessories;
			$newDiscountPrice=$discountPrice;
            $newMinimumStay = (isset($typologyDayPrice['minimum_nights'])) ? $typologyDayPrice['minimum_nights'] : 1;
            $newCheckin = (isset($typologyDayPrice['checkin'])) ? $typologyDayPrice['checkin'] : null;
            $newCheckout = (isset($typologyDayPrice['checkout'])) ? $typologyDayPrice['checkout'] : null;
            
			if($onlyPrices) {
				$isDifferent = (bool)(($price !== $newPrice) || ($norefoundprice !== $newNoRefoundPrice) || ($extrapersonprice !== $newExtraPersonPrice));
			}
			else {
				$isDifferent = (bool)(($price !== $newPrice) || ($norefoundprice !== $newNoRefoundPrice) || ($extrapersonprice !== $newExtraPersonPrice) || ($minimumStay !== $newMinimumStay));
			}
			
            if($isDifferent) {
                if($price) {
                    $pricesPeriods[] = array(
                        'price' => $price,
						'price_no_accessories' => $price_no_accessories,
                        'norefoundprice' => $norefoundprice,
						'norefoundprice_no_accessories' => $norefoundprice_no_accessories,
						'discount'=>$discount,
						'extrapersonprice' => $extrapersonprice,
                        'minimum_stay' => $minimumStay,
                        'start_date' => $initialPriceDay,
                        'end_date' => $endPriceDay,
                        'checkin' => $newCheckin,
                        'checkout' => $newCheckout,
                    );
                }
                $initialPriceDay = $typologyDayPrice['day'];
                $price = $newPrice;
				$price_no_accessories=$newPriceNoAccessories;
                $norefoundprice = $newNoRefoundPrice;
				$norefoundprice_no_accessories=$newNoRefoundPriceNoAccessories;
				$discount = $newDiscountPrice;
				$extrapersonprice = $newExtraPersonPrice;
                $minimumStay = $newMinimumStay;
                $checkin = $newCheckin;
                $checkout = $newCheckout;
            }
            $endPriceDay = $typologyDayPrice['day'];
        }
        if($price) {
            $pricesPeriods[] = array(
                'price' => $price,
				'price_no_accessories' => $price_no_accessories,
                'norefoundprice' => $norefoundprice,
				'norefoundprice_no_accessories' => $norefoundprice_no_accessories,
				'discount'=>$discount,
				'extrapersonprice' => $extrapersonprice,
                'minimum_stay' => $minimumStay,
                'start_date' => $initialPriceDay,
                'end_date' => $endPriceDay,
                'checkin' => $checkin,
                'checkout' => $checkout,
            );
        }
        return $pricesPeriods;
    }
    
    /**
     * modify property portal parameters
     * @param  integer $propertyId Property id
     * @param string $params['hotel_id']
     * @param string $params['room_id']
     */
    public function modifyPropertyPortal($propertyId,$params = array())
    {
        $endPoint = $this->getEndPoint('typology_portal');
        $this->api(sprintf($endPoint, $propertyId), 'PUT', $params);
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
	
	public function getContact($contactId)
    {
        $endPoint = $this->getEndPoint('contact');
        return $this->api(sprintf($endPoint . '%s',$contactId));
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
     * get bookings by external id of the portal
     * @param  string $external_id Booking external id
     * @param  string $options['status'] Booking status. Filter booking only if is one of the submitted status, can be multiple separated by comma (,). Values [prebooked,booked,cancelled]
     * @return array
     */
    public function getBookingsByExternalId($external_id,$options = array())
    {
        $params = array();
        if(isset($options['status'])) {
            $params['status'] = $options['status'] ? $options['status']  : "";
        }
        
        $endPoint = $this->getEndPoint('bookings_external', array($external_id));
        return $this->api(sprintf($endPoint . '?%s',http_build_query($params)));
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

    public function getBookings($params = array())
    {
        $endPoint = $this->getEndPoint('booking');
        return $this->api($endPoint, 'GET', $params);
    }

    public function getBooking($bookingId)
    {
        $endPoint = $this->getEndPoint('booking_modify', array($bookingId));
        return $this->api($endPoint, 'GET');
    }
    
    /**
     * modify booking
     * to modify dates, set params "date_in" and "date_out"
     * to cancel booking, set param status=cancelled
     * @param  integer $bookingId Booking id
     */ 
    public function modifyBooking($bookingId,$params = array())
    {
        $endPoint = $this->getEndPoint('booking_modify');
        return $this->api(sprintf($endPoint, $bookingId), 'PUT', $params);
    }
	
	/**
     * Inserts booking card. To get fields, consult online documentation at  
     * https://hub.net2rent.com/doc/portal.php?action=show_form&filteru=&apiurl=hub.net2rent.com&usr=portal1&pas=portal1&section=bookings&call=POST+%2Fbookings%2F%3Abooking_id%2Fcards%2F
     */
	public function insertBookingCard($bookingId,array $bookingCardOptions)
    {
        $endPoint = $this->getEndPoint('booking_card');
        return $this->api(sprintf($endPoint,$bookingId), 'POST', $bookingCardOptions);
    }
	
	/**
     * get bookingrequest by external ref and external id of the portal
     * @param  string $external_ref Booking external id
     * @param  string $external_id Booking external id
     * @return array 
     */
    public function getBookingRequestByExternalRefExternalId($external_ref,$external_id)
    {        
        $endPoint = $this->getEndPoint('bookingrequest_external_ref_id', array($external_ref,$external_id));
        return $this->api(sprintf($endPoint));
    }    
    
    public function insertBookingRequest($params = array())
    {
        $endPoint = $this->getEndPoint('bookingrequest');
        return $this->api($endPoint.'/', 'POST', $params);
    }
	
	/**
     * modify bookingrequest
     * @param  integer $bookingRequestId Booking id     */ 
    public function modifyBookingrequest($bookingRequestId,$params = array())
    {
        $endPoint = $this->getEndPoint('bookingrequest_modify');
        return $this->api(sprintf($endPoint, $bookingRequestId), 'PUT', $params);
    }
}
