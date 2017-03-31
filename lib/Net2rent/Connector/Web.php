<?php
namespace Net2rent\Connector;

class Web extends AbstractConnector
{

    protected $_endPoints = array(
        'company' => '/companies/{{company}}',
        'agency' => '/agencies/',
        'agencies' => '/companies/{{company}}/buildings/%s/agencies',
        'cities' => '/companies/{{company}}/properties-cities',
        'properties' => '/companies/{{company}}/typologies',
        'property' => '/typologies/%s',
        'properties_availables' => '/companies/{{company}}/lists/typologies_availability',
        'property_available' => '/companies/{{company}}/lists/typologies_availability/%s',
        'property_images' => '/typologies/%s/images',
        'property_equipment' => '/properties/%s/equipment',        
        'property_propertystatus' => '/properties/%s/propertystatus',
        'property_availability' => '/typologies/%s/pricecalendar',
        'property_accessories' => '/properties/%s/propertyaccessories',
        'season_days' => '/seasons/%d/days',
        'contacts' => '/companies/{{company}}/contacts',
        'contact' => '/contacts/',
        'booking' => '/bookings/',
        'bookings_contact' => '/contacts/%s/bookings',
        'bookings_guaranteed' => '/bookings/%s/guaranteed',
        'booking_search' => '/companies/{{company}}/bookings',
        'booking_person' => '/bookings/%s/people/',
        'booking_accessory' => '/bookings/%s/accessories/',
        'payments' => '/bookings/%s/payments',
        'payment' => '/bookings/%s/payments/',
        'comments' => '/typologies/%s/clientcomments',
        'puntualoffers' => '/companies/{{company}}/puntualoffers',
        'puntualoffers_property' => '/typologies/%s/puntualoffers',
        'puntualoffer_property' => '/typologies/%s/puntualoffer',
        'discounts' => '/companies/{{company}}/discounts',
        'discounts_property' => '/typologies/%s/discounts',
        'properties_building_types' => '/companies/{{company}}/properties-building-types',
        'properties_zones' => '/companies/{{company}}/properties-zones',
        'hear_about_us' => '/enums/14/values',
        'companies_minimum_nights' => '/companies/{{company}}/baseprices/minimum_nights',
        'companies_entry_days' => '/companies/{{company}}/baseprices/entry_days',
        'companies_out_days' => '/companies/{{company}}/baseprices/out_days'
    );
    
    public function getCompany()
    {
        $endPoint = $this->getEndPoint('company');
        return $this->api($endPoint);
    }
    
    public function getAgency($agencyId)
    {
        $endPoint = $this->getEndPoint('agency');
        return $this->api(sprintf($endPoint . '%s',$agencyId));
    }
    
    public function getAgenciesProperty($buildingId)
    {
        $endPoint = $this->getEndPoint('agencies');
        return $this->api(sprintf($endPoint,$buildingId));
    }
    
    /**
     * Gets property availability and prices
     *
     * @param  string  $propertyId
     * @param  date  $options['from'] First date to get availability and prices. Format YYYY-MM-DD. Optional, if not set gets one year
     * @param  date  $options['to'] Last date to get availability and prices. Format YYYY-MM-DD. Optional, if not set gets one year
     * @return array  (items)
     */
    public function getAvailability($propertyId, array $options = array())
    {
        $endPoint = $this->getEndPoint('property_availability', array(
            $propertyId
        ));

        $params = array();
        $params['from']=date('Y-m-d');
        $params['to']=date('Y-m-d',strtotime(date('Y-m-d').' + 1 year'));
        if (isset($options['from'])) {
            $params['from'] = $this->checkDateFormat($options['from']);
        }
        if (isset($options['to'])) {
            $params['to'] = $this->checkDateFormat($options['to']);
        }
                
        $baseprices = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
        $availabilityPricesDays=array();
        
        // Get only necessary fields
        foreach($baseprices as $baseprice) {
            $availabilityPriceDay['day']=$baseprice['day']; // day
            $availabilityPriceDay['rentprice']=$baseprice['rentprice']; // rent price for this day
            $availabilityPriceDay['norefoundprice']=$baseprice['norefoundprice']; // no refund price for this day
            $availabilityPriceDay['discountprice']=$baseprice['discountprice']; // discount price for this day
            $availabilityPriceDay['minimum_nights']=$baseprice['minimum_nights']; // minimum booking nights if is this entry day
            $availabilityPriceDay['checkin']=$baseprice['checkin']; // 1 if possible checkin this day, 0 if not
            $availabilityPriceDay['checkout']=$baseprice['checkout']; // 1 if possible checkout this day, 0 if not
            $availabilityPriceDay['available']=(int)$baseprice['available']>0 ? 1 : 0; // available: 1=yes, 0=no
            
            $availabilityPricesDays[]=$availabilityPriceDay;
        }        
        
        return $availabilityPricesDays;
    }
    
    /**
     * Gets property rates for seasons
     *
     * @param  array  $property Property array obtained with getProperty method
     * @param  date  $options['from'] First date to get rates and seasons. Format YYYY-MM-DD. Optional, if not set gets one year
     * @param  date  $options['to'] Last date to get rates and seasons. Format YYYY-MM-DD. Optional, if not set gets one year
     * @param  bool  $options['overwritten_priority'] If set, returns only overwritten prices, else returns rates and overwitten prices merged
     * @param  bool  $options['only_contract'] If set, returns only rates and overwritten prices within the days with contract
     * @param  string $options['lg'] Language to get season names. Valid values [ca,es,en,fr,de,nl,it,ru]
     * @return array  (items)
     */
    public function getRates(array $property, array $options = array())
    {

        $params = array();
        if (isset($options['from'])) {
            $params['from'] = $this->checkDateFormat($options['from']);
        }
        if (isset($options['to'])) {
            $params['to'] = $this->checkDateFormat($options['to']);
        }
        if (isset($options['overwritten_priority'])) {
            $params['overwritten_priority'] = (int)$options['overwritten_priority'];
        } else {
            $params['overwritten_priority']=0;
        }
        if (isset($options['only_contract'])) {
            $params['only_contract'] = (int)$options['only_contract'];
        } else {
            $params['only_contract']=0;
        }
        $params['orderby']='seasondate.day';
        
        $seasonsRates=array();
        
        // if is set only_contract, get contract days
        $contractDaysIndexed=array();
        if($params['only_contract']) {
            $endPoint=sprintf('%s/properties/%s/propertystatus', 
                            $this->apiBaseUrl, 
                            $property['first_property_id']
                    );     

            // get propertystatus to obtain contract days
            $contractDays = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
            
            // index propertystatus by day
            foreach($contractDays as $contractDay) {
                $contractDaysIndexed[$contractDay['day']]=$contractDay;
            }
        }
        
        // get days with price overwritten
        $endPoint2=sprintf('%s/typologies/%s/pricecalendar?overwritten=1', 
                        $this->apiBaseUrl, 
                        $property['id']
                );
        $overwrittenDays = $this->api(sprintf($endPoint2 . '%s', http_build_query($params)));
        $overwrittenDaysIndexed=array();
        // index overwritten days by day
        foreach($overwrittenDays as $overwrittenDay) {
            $overwrittenDaysIndexed[$overwrittenDay['day']]=$overwrittenDay;
        }
        
        // get baseprices
        $endPoint3=sprintf('%s/typologies/%s/pricecalendar?', 
                        $this->apiBaseUrl, 
                        $property['id']
                );
        $basePrices = $this->api(sprintf($endPoint3 . '%s', http_build_query($params)));
        $basePricesDaysIndexed=array();
        // index overwritten days by day
        foreach($basePrices as $basePrice) {
            $basePricesDaysIndexed[$basePrice['day']]=$basePrice;
        }
        
        if(isset($property['agency_id']) && $property['agency_id']) {
        
            $endPoint=sprintf('%s/agencies/%s/seasons/calendar', 
                            $this->apiBaseUrl, 
                            $property['agency_id']
                    );     

            // get season days
            $seasonDays = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));

            $params_season=array();
            $params_season['lg']=$options['lg'];
            if($seasonDays) {
                $seasonId=0;
                $i=-1;
                $j=0;
                $prev='';
                foreach($seasonDays as $day) {
                    if(
                       (!$params['only_contract'] || 
                       ($params['only_contract'] && isset($contractDaysIndexed[$day['day']]) && (int)$contractDaysIndexed[$day['day']]['contract']))
                        && !isset($overwrittenDaysIndexed[$day['day']])
                    ) {
                        if($day['season_id']==$seasonId) {
                            $next=date('Y-m-d', strtotime('+1 day', strtotime($prev)));
                            if($day['day']!=$next) {
                                $seasonsRates[$i]['dates'][$j]['end_day']=$prev;
                                $j++;
                                $seasonsRates[$i]['dates'][$j]['start_day']=$day['day'];
                            }
                            $prev=$day['day'];
                        } else {
                            if($i>-1) {
                                $seasonsRates[$i]['dates'][$j]['end_day']=$prev;
                            }
                            $j=0;
                            $seasonId=$day['season_id'];
                            $i=$seasonId;
                            $params_season['season_id']=$seasonId;
                                      
                            // if season is created in dates before, set index for new period
                            if(isset($seasonsRates[$i])) {
                                foreach($seasonsRates[$i]['dates'] as $dates) {
                                    if($dates) { }
                                    $j++;
                                }
                            }

                            $endPointSeason=sprintf('%s/typologies/%s/season_price', 
                                $this->apiBaseUrl, 
                                $property['id']
                            );
                            $info=$this->api(sprintf($endPointSeason . '?%s', http_build_query($params_season)));
                            $minimum_nights=isset($basePricesDaysIndexed[$day['day']]) ? $basePricesDaysIndexed[$day['day']]['minimum_nights'] : null; 
                            
                            // create season only if was not created in early dates
                            if($j==0) {
                                $seasonsRates[$i]=array('season_id'=>$seasonId, 'name'=>$info['season_name'], 'rentprice'=>$info['rentprice'], 'day_week'=>$info['day_week'],'minimum_nights'=>$minimum_nights );
                            }
                            $seasonsRates[$i]['dates'][$j]=array('start_day'=>$day['day']);
                            $prev=$day['day'];
                        }
                    }
                }
                if($prev) {
					$seasonsRates[$i]['dates'][$j]['end_day']=$prev;
                }
            }
        }
     
        $overwerittenRates=array();
        
        if($overwrittenDays) {
            $price = null;
            $i = -1;
            $j = 0;
            $prev = '';
            foreach($overwrittenDays as $day) {
                if(!$params['only_contract'] || 
                   ($params['only_contract'] && isset($contractDaysIndexed[$day['day']]) && (int)$contractDaysIndexed[$day['day']]['contract'])) 
                {
                    if($day['rentprice'] == $price) {
                        $next = date('Y-m-d', strtotime('+1 day', strtotime($prev)));
                        if($day['day'] != $next)
                        {
                            $overwerittenRates[$i]['dates'][$j]['end_day'] = $prev;
                            $j++;
                            $overwerittenRates[$i]['dates'][$j]['start_day'] = $day['day'];
                        }
                        $prev = $day['day'];
                    } else {
                        if($i > -1) {
                            $overwerittenRates[$i]['dates'][$j]['end_day'] = $prev;
                        }
                        $j = 0;
                        $i++; 
                        $price = $day['rentprice'];

                        $overwerittenRates[$i] = array('rentprice' => $price,'name'=>'','season_id'=>0,'day_week'=>'day','minimum_nights'=>$day['minimum_nights']);
                        $overwerittenRates[$i]['dates'][$j] = array('start_day' => $day['day']);
                        $prev = $day['day'];
                    }
                }
            }
            $overwerittenRates[$i]['dates'][$j]['end_day'] = $prev;
        }
        
        if($params['overwritten_priority'] && !empty($overwerittenRates)) {        
            $seasonsOverwrittenRates=$overwerittenRates;
        } else {
            $seasonsOverwrittenRates=array_merge($seasonsRates,$overwerittenRates);
        }
            
        return $seasonsOverwrittenRates;
    }
        
    /**
     * Gets contacts from a company
     *
     * @param  string $options['email'] Search contacts with this email
     * @param  string $options['name'] Search contacts with this name
     * @return array  (items)
     */
    public function getContacts(array $options = array())
    {
        $endPoint = $this->getEndPoint('contacts');

        $params = array();
        $params['active']=1;
        if (isset($options['email'])) {
            $params['email'] = $options['email'];
        }
        $contacts = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
        
        return array(
            'items' => $contacts
        );
    }
    
    public function getContact($contactId)
    {
        $endPoint = $this->getEndPoint('contact');
        return $this->api(sprintf($endPoint . '%s',$contactId));
    }
    
    public function insertContact(array $contactOptions)
    {
        $endPoint = $this->getEndPoint('contact');
        return $this->api($endPoint, 'POST', $contactOptions);
    }
    
    public function updateContact($contactId,array $contactOptions)
    {
        $endPoint = $this->getEndPoint('contact');
        $this->api(sprintf($endPoint . '%s',$contactId), 'PUT', $contactOptions);
    }
    
    public function getBooking($bookingId)
    {
        $endPoint = $this->getEndPoint('booking');
        return $this->api(sprintf($endPoint . '%s',$bookingId));
    }
    
    /**
     * Gets bookings from a contact
     *
     * @param  string $options['offset'] 
     * @param  string $options['limit']
     * @param  string $options['status']
     * @param  string $options['date_type'] 
     * @param  string $options['date_in']
     * @param  string $options['date_out']
     * @param  string $options['orderby']
     * @param  string $options['orderdesc']
     * @return array  (items)
     */
    public function getBookingsContact($contactId,array $options = array())
    {
        $params = array();
        $params['offset']=0;
        if (isset($options['offset'])) {
            $params['offset'] = $options['offset'];
        }
        $params['limit']=-1;
        if (isset($options['limit'])) {
            $params['limit'] = $options['limit'];
        }
        $params['status']='';
        if (isset($options['status'])) {
            $params['status'] = $options['status'];
        }
        $params['date_type']=0;
        if (isset($options['date_type'])) {
            $params['date_type'] = $options['date_type'];
        }
        $params['date_in']='';
        if (isset($options['date_in'])) {
            $params['date_in'] = $options['date_in'];
        }
        $params['orderby']='offer.date_in';
        if (isset($options['orderby'])) {
            $params['orderby'] = $options['orderby'];
        }
        $params['orderdesc']=0;
        if (isset($options['orderdesc'])) {
            $params['orderdesc'] = $options['orderdesc'];
        }
        
        $endPoint = $this->getEndPoint('bookings_contact');
        $bookings = $this->api(sprintf($endPoint . '?%s',$contactId,http_build_query($params)));
        
        return array(
            'items' => $bookings,
            'total' => count($bookings)
        );
    }
    
    public function getGuaranteedBookingsBooking($bookingId)
    {
        $endPoint = $this->getEndPoint('bookings_guaranteed');
        $bookings = $this->api(sprintf($endPoint,$bookingId));
        
        return array(
            'items' => $bookings,
            'total' => count($bookings)
        );
    }
    
    public function searchBooking($bookingRef,$email)
    {
        $endPoint = $this->getEndPoint('booking_search');
        $booking=$this->api(sprintf($endPoint . '?ref_string=%s',urlencode($bookingRef))); 
        
        // only return booking if email matches
        if($booking && !empty($booking) && $booking[0]['contact_email']==$email) {
            return $booking[0];
        } else {
            return null;
        }
    }
    
    /**
     * Inserts a booking. To get fields, consult online documentation at  
     * https://hub.net2rent.com/doc/employee.php?action=show_form&filteru=&apiurl=hub.net2rent.com&usr=admin%40company.com&pas=admin_company&section=bookings&call=POST+%2Fbookings%2F
     */
    public function insertBooking(array $bookingOptions)
    {
        $endPoint = $this->getEndPoint('booking');
        return $this->api($endPoint, 'POST', $bookingOptions);
    }
    
    /**
     * Inserts a booking person. To get fields, consult online documentation at  
     * https://hub.net2rent.com/doc/employee.php?action=show_form&filteru=&apiurl=hub.net2rent.com&usr=admin%40company.com&pas=admin_company&section=bookings&call=POST+%2Fbookings%2F%3Abooking_id%2Fpeople%2F
     */
    public function insertBookingPerson(array $bookingPersonOptions)
    {
        $endPoint = $this->getEndPoint('booking_person');
        return $this->api(sprintf($endPoint,$bookingPersonOptions['booking_id']), 'POST', $bookingPersonOptions);
    }
    
    /**
     * Inserts a booking accessory. To get fields, consult online documentation at  
     * https://hub.net2rent.com/doc/employee.php?action=show_form&filteru=&apiurl=hub.net2rent.com&usr=admin%40company.com&pas=admin_company&section=bookings&call=POST+%2Fbookings%2F%3Abooking_id%2Faccessories%2F
     */
    public function insertBookingAccessory(array $bookingAccessoryOptions)
    {
        $endPoint = $this->getEndPoint('booking_accessory');
        return $this->api(sprintf($endPoint,$bookingAccessoryOptions['booking_id']), 'POST', $bookingAccessoryOptions);
    }
    
    public function getPayments($bookingId)
    {
        $endPoint = $this->getEndPoint('payments');
        $payments=$this->api(sprintf($endPoint,$bookingId), 'GET');
        
        return array(
            'total' => count($payments),
            'items' => $payments
        );
    }
    
    public function getPayment($bookingId,$paymentId)
    {
        $endPoint = $this->getEndPoint('payment');
        return $this->api(sprintf($endPoint.'?%s',$bookingId,$paymentId), 'GET');
    }
    
    public function insertPayment($bookingId,array $paymentOptions)
    {
        $endPoint = $this->getEndPoint('payment');
        return $this->api(sprintf($endPoint,$bookingId), 'POST', $paymentOptions);
    }
    
    public function cancelBooking($bookingId,array $bookingOptions)
    {
        $endPoint = $this->getEndPoint('booking');
        return $this->api(sprintf($endPoint.'%s',$bookingId), 'PUT', $bookingOptions);
    }
    
    /**
     * Gets comments from a property
     *
     * @param  string $options['public'] Filter by public. 1=public, 0=no public, null=all (default=1)
     * @param  string $options['lang'] Filter by comment language, valid values: ca,es,en,fr,de,nl,it,ru
     * @param  string $options['orderby'] Order comments field
     * @param  string $options['orderby'] Order comments field
     * @param  string $options['orderdesc'] Order comments ASC (0) or DESC (1)
     * @return array  (total|items)
     */
    public function getComments($propertyId, array $options = array())
    {
        $endPoint = $this->getEndPoint('comments');

        $params = array();
        $params['public']=1;
        if(isset($options['public'])) {
            $params['public'] = (int)$options['public'];
        }
        $params['orderby']='clientcomment.creation_date';
        if(isset($options['orderby'])) {
            $params['orderby'] = $options['orderby'];
        }
        $params['orderdesc']=1;
        if(isset($options['orderdesc'])) {
            $params['orderdesc'] = $options['orderdesc'];
        }
        
        $comments = $this->api(sprintf($endPoint . '?%s', $propertyId, http_build_query($params)));
        $commentsTotal = $this->api(sprintf($endPoint . '/size?%s', $propertyId, http_build_query($params)));
        
        return array(
            'total' => $commentsTotal['size'],
            'items' => $comments
        );
    }
    
    public function insertComment($propertyId,array $commentOptions)
    {
        $endPoint = $this->getEndPoint('comments');
        return $this->api(sprintf($endPoint,$propertyId).'/', 'POST', $commentOptions);
    }
    
    /**
     * Gets putualoffers from a company
     *
     * @param  bool $options['active'] Filter by active
     * @param  bool $options['unexpired'] Filter by unexpired
     * @param  integer $options['limit'] Limit results
     * @param  integer  $options['max_w'] Max width of image
     * @param  integer  $options['max_h'] Max height of image
     * @param  integer  $options['quality'] JPEG quality percent of image
     * @return array  (total|items)
     */
    public function getPuntualoffers(array $options = array())
    {
        $endPoint = $this->getEndPoint('puntualoffers');

        $params = array();
        $params['active']=1;
        if(isset($options['active'])) {
            $params['active'] = $options['active'];
        }   
        $params['unexpired']=1;
        if(isset($options['unexpired'])) {
            $params['unexpired'] = $options['unexpired'];
        }  
        $params['limit']=-1;
        if(isset($options['limit'])) {
            $params['limit'] = (int)$options['limit'];
        }  
        $params['orderby']='puntualoffer.date_in';
        if(isset($options['orderby'])) {
            $params['orderby'] = $options['orderby'];
        }
        $params['orderdesc']=0;
        if(isset($options['orderdesc'])) {
            $params['orderdesc'] = $options['orderdesc'];
        }
        $params['max_w']=4000;
        if (isset($options['max_w'])) {
            $params['max_w'] = $options['max_w'];
        }
        $params['max_h']=3000;
        if (isset($options['max_h'])) {
            $params['max_h'] = $options['max_h'];
        }
        $params['quality']=80;
        if (isset($options['quality'])) {
            $params['quality'] = $options['quality'];
        }

        $puntualoffers = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
        $puntualoffersTotal = $this->api(sprintf($endPoint . '/size?%s', http_build_query($params)));
        
        $puntualoffers_with_image=array();
        foreach($puntualoffers as $puntualoffer) {
            $puntualoffer['name_lg'] = array(
                        'es' => strip_tags($puntualoffer['name_es']) ,
                        'ca' => strip_tags($puntualoffer['name_ca']) ,
                        'en' => strip_tags($puntualoffer['name_en']) ,
                        'fr' => strip_tags($puntualoffer['name_fr']) ,
                        'de' => strip_tags($puntualoffer['name_de']) ,
                        'nl' => strip_tags($puntualoffer['name_nl']) ,
                        'it' => strip_tags($puntualoffer['name_it']) ,
                        'ru' => strip_tags($puntualoffer['name_ru'])
            );
            $puntualoffer['subtitle_lg'] = array(
                        'es' => strip_tags($puntualoffer['subtitle_es']) ,
                        'ca' => strip_tags($puntualoffer['subtitle_ca']) ,
                        'en' => strip_tags($puntualoffer['subtitle_en']) ,
                        'fr' => strip_tags($puntualoffer['subtitle_fr']) ,
                        'de' => strip_tags($puntualoffer['subtitle_de']) ,
                        'nl' => strip_tags($puntualoffer['subtitle_nl']) ,
                        'it' => strip_tags($puntualoffer['subtitle_it']) ,
                        'ru' => strip_tags($puntualoffer['subtitle_ru'])
            );
            $puntualoffer['text_lg'] = array(
                        'es' => strip_tags($puntualoffer['text_es']) ,
                        'ca' => strip_tags($puntualoffer['text_ca']) ,
                        'en' => strip_tags($puntualoffer['text_en']) ,
                        'fr' => strip_tags($puntualoffer['text_fr']) ,
                        'de' => strip_tags($puntualoffer['text_de']) ,
                        'nl' => strip_tags($puntualoffer['text_nl']) ,
                        'it' => strip_tags($puntualoffer['text_it']) ,
                        'ru' => strip_tags($puntualoffer['text_ru'])
            );
            
            $puntualoffer['image']=sprintf('%s/promotions/puntualoffers/%s/image?max_w=%s&max_h=%s&quality=%s',
                        $this->apiBaseUrl,
                        $puntualoffer['id'],
                        $params['max_w'],
                        $params['max_h'],
                        $params['quality']
            );
            
            // if puntualoffer has no image, get image from typology
            $file_headers = get_headers($puntualoffer['image']);

            if($file_headers[0] != 'HTTP/1.1 200 OK') {
                $puntualoffer['image']=(isset($puntualoffer['image_id'])) ? sprintf('%s/typologies/%s/images/%s/image.jpg?max_w=%s&max_h=%s&quality=%s',
                            $this->apiBaseUrl,
                            $puntualoffer['typology_id'],
                            $puntualoffer['image_id'],
                            $params['max_w'],
                            $params['max_h'],
                            $params['quality']
                            )
                    : null;
            }
            
            $puntualoffers_with_image[]=$puntualoffer;
        }
        
        return array(
            'total' => $puntualoffersTotal['size'],
            'items' => $puntualoffers_with_image
        );
    }

	/**
     * Gets putualoffers from a property
     *
     * @param  bool $options['active'] Filter by active
     * @param  bool $options['unexpired'] Filter by unexpired
     * @param  date  $options['start_day'] Filter by start day greater or equal
     * @param  date  $options['end_day'] Filter by end day less or equal
     * @return array  (total|items)
     */
    public function getPuntualoffersProperty($propertyId,array $options = array())
    {
        $endPoint = $this->getEndPoint('puntualoffers_property');

        $params = array();
        $params['active']=1;
        if(isset($options['active'])) {
            $params['active'] = $options['active'];
        }   
        $params['unexpired']=1;
        if(isset($options['unexpired'])) {
            $params['unexpired'] = $options['unexpired'];
        } 
        $params['start_day']='';
        if (isset($options['start_day'])) {
            $params['start_day'] = $options['start_day'];
        }
        $params['end_day']='';
        if (isset($options['end_day'])) {
            $params['end_day'] = $options['end_day'];
        }

        $puntualoffers = $this->api(sprintf($endPoint . '?%s',$propertyId, http_build_query($params)));

        
        return array(
            'total' => count($puntualoffers),
            'items' => $puntualoffers
        );
    }
    
    /**
     * Gets puntualoffer within dates from a property if puntualoffer exists
     *
     * @param  bool $options['active'] Filter by active
     * @param  date  $options['start_day'] Filter by start day greater or equal
     * @param  date  $options['end_day'] Filter by end day less or equal
     * @return array  (total|items)
     */
    public function getPuntualofferProperty($propertyId,array $options = array())
    {
        $endPoint = $this->getEndPoint('puntualoffer_property');

        $params = array();
        $params['active']=1;
        if(isset($options['active'])) {
            $params['active'] = $options['active'];
        }   
        $params['start_day']='';
        if (isset($options['start_day'])) {
            $params['start_day'] = $options['start_day'];
        }
        $params['end_day']='';
        if (isset($options['end_day'])) {
            $params['end_day'] = $options['end_day'];
        }

        $puntualoffers = $this->api(sprintf($endPoint . '?%s',$propertyId, http_build_query($params)));

        
        return array(
            'total' => count($puntualoffers),
            'items' => $puntualoffers
        );
    }
    
    /**
     * Gets discounts
     *
     * @param  bool $options['active'] Filter by active
     * @param  bool $options['unexpired'] Filter by unexpired
     * @param  string $options['orderby'] Order discounts
     * @return array  (total|items)
     */
    public function getDiscounts(array $options = array())
    {
        $endPoint = $this->getEndPoint('discounts');

        $params = array();
        $params['active']=1;
        if(isset($options['active'])) {
            $params['active'] = $options['active'];
        }   
        $params['unexpired']=1;
        if(isset($options['unexpired'])) {
            $params['unexpired'] = $options['unexpired'];
        } 
        $params['orderby']='discount.end_day';
        if(isset($options['orderby'])) {
            $params['orderby'] = $options['orderby'];
        }
        $params['orderdesc']=0;
        if(isset($options['orderdesc'])) {
            $params['orderdesc'] = $options['orderdesc'];
        }
        
        $params['limit']=200;
        $discounts = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
        
        return array(
            'total' => count($discounts),
            'items' => $discounts
        );
    }
    
    /**
     * Gets discounts from a property
     *
     * @param  bool $options['active'] Filter by active
     * @param  bool $options['unexpired'] Filter by unexpired
     * @param  date  $options['start_day'] Filter by start day greater or equal
     * @param  date  $options['end_day'] Filter by end day less or equal
     * @param  date  $options['apply_final'] Filter by apply_final. Values 1=true, 0=false, default value=true
     * @param  date  $options['apply_ttoo'] Filter by apply_ttoo. Values 1=true, 0=false, default value=false
     * @param  date  $options['apply_central'] Filter by apply_central. Values 1=true, 0=false, default value=false
     * @return array  (total|items)
     */
    public function getDiscountsProperty($propertyId,array $options = array())
    {
        $endPoint = $this->getEndPoint('discounts_property');

        $params = array();
        $params['active']=1;
        if(isset($options['active'])) {
            $params['active'] = $options['active'];
        }   
        $params['unexpired']=1;
        if(isset($options['unexpired'])) {
            $params['unexpired'] = $options['unexpired'];
        } 
        $params['start_day']='';
        if (isset($options['start_day'])) {
            $params['start_day'] = $options['start_day'];
        }
        $params['end_day']='';
        if (isset($options['end_day'])) {
            $params['end_day'] = $options['end_day'];
        }        
        $params['apply_final']=true;
        if (isset($options['apply_final'])) {
            $params['apply_final'] = $options['apply_final'];
        }
        $params['apply_ttoo']=false;
        if (isset($options['apply_ttoo'])) {
            $params['apply_ttoo'] = $options['apply_ttoo'];
        }
        $params['apply_central']=false;
        if (isset($options['apply_central'])) {
            $params['apply_central'] = $options['apply_central'];
        }

        $discounts = $this->api(sprintf($endPoint . '?%s',$propertyId, http_build_query($params)));

        $discounts_filtered=array();
        foreach($discounts as $discount) {
            if(
                ( (bool)$params['apply_final']===true && $discount['apply_final'] )
                || ( (bool)$params['apply_ttoo']===true && $discount['apply_ttoo'] )     
                || ( (bool)$params['apply_central']===true && $discount['apply_central'] )     
            ) {
                $discounts_filtered[]=$discount;
            }
        }
        
        return array(
            'total' => count($discounts_filtered),
            'items' => $discounts_filtered
        );
    }
    
    /**
     * Get building types of active properties
     * @param  string $options['commercialization_type'] Valid values: [rental, sale, rental_sale, property_management, rental_property_management], can be multiple separed by , (comma)
     * @param
     * @return array
     */
    public function getPropertiesBuildingTypes(array $options = array())
    {
        $endPoint = $this->getEndPoint('properties_building_types');

        $params = array();
        if(isset($options['commercialization_type'])) {
            $params['commercialization_type'] = $options['commercialization_type'];
        }
        return $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
    }
    
    /**
     * Get building types of active properties
     * @param  string $options['city'] City of the properties zones, can be multiple values separated by comma 
     * @param  string $options['commercialization_type'] Valid values: [rental, sale, rental_sale, property_management, rental_property_management], can be multiple separed by , (comma)
     * @return array
     */
    public function getZones(array $options = array())
    {
        $endPoint = $this->getEndPoint('properties_zones');

        $params = array();
        if(isset($options['city'])) {
            $params['city'] = $options['city'];
        }
        if(isset($options['commercialization_type'])) {
            $params['commercialization_type'] = $options['commercialization_type'];
        }
        return $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
    }
    
    public function getHearAboutUsValues()
    {
        $endPoint = $this->getEndPoint('hear_about_us');
        return $this->api($endPoint);
    }
    
    public function getMinimumNights()
    {
        $endPoint = $this->getEndPoint('companies_minimum_nights');
        $minimumNights=$this->api($endPoint);
        return isset($minimumNights['minimum_nights']) ? (int)$minimumNights['minimum_nights'] : 1; 
    }
    
    public function getEntryDays()
    {
        $endPoint = $this->getEndPoint('companies_entry_days');
        $entryDays=$this->api($endPoint);
        return isset($entryDays['entry_days']) ? $entryDays['entry_days'] : array() ;
    }
    
    public function getOutDays()
    {
        $endPoint = $this->getEndPoint('companies_out_days');
        $outDays=$this->api($endPoint);
        return isset($outDays['out_days']) ? $outDays['out_days'] : array();
    }
}
