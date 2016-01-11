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
        'booking_search' => '/companies/{{company}}/bookings',
        'booking_person' => '/bookings/%s/people/',
        'booking_accessory' => '/bookings/%s/accessories/',
        'payment' => '/bookings/%s/payments/',
        'comments' => '/typologies/%s/clientcomments',
        'puntualoffers' => '/companies/{{company}}/puntualoffers',
        'puntualoffers_property' => '/typologies/%s/puntualoffers',
        'puntualoffer_property' => '/typologies/%s/puntualoffer',
        'discounts' => '/companies/{{company}}/discounts',
        'discounts_property' => '/typologies/%s/discounts',
        'properties_building_types' => '/companies/{{company}}/properties-building-types',
        'properties_zones' => '/companies/{{company}}/properties-zones',
        'hear_about_us' => '/enums/14/values'
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
            $availabilityPriceDay['entry_days']=$baseprice['entry_days']; // possible entry days: -1=any day, 0=sunday, 1=monday, 2=tuesday, 3=wednesday, 4=thursday, 5=friday, 6=saturday
            $availabilityPriceDay['out_days']=$baseprice['out_days']; // possible out days: -1=any day, 0=sunday, 1=monday, 2=tuesday, 3=wednesday, 4=thursday, 5=friday, 6=saturday
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
                            
                            // create season only if was not created in early dates
                            if($j==0) {
                                $seasonsRates[$i]=array('season_id'=>$seasonId, 'name'=>$info['season_name'], 'rentprice'=>$info['rentprice'], 'day_week'=>$info['day_week'], 'minimum_nights'=>$info['minimum_nights'] >-1 ? $info['minimum_nights'] : $day['minimum_nights'] );
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
     * Gets property availability and prices
     *
     * @param  string  $propertyId
     * @param  date  $options['mandatory'] Filter by mandatory (1=yes, 0=no)
     * @param  lg    $options['mandatory'] Language of the accessory name (values: ca,es,en,fr,de,nl,ru,it)
     * @param  date  $options['from'] First date to get availability and prices. Format YYYY-MM-DD. Optional, if not set gets one year
     * @param  date  $options['to'] Last date to get availability and prices. Format YYYY-MM-DD. Optional, if not set gets one year
     * @return array  (items)
     */
    public function getPropertyAccessories($propertyId, array $options = array())
    {
        $endPoint = $this->getEndPoint('property_accessories', array(
            $propertyId
        ));

        $params = array();
        $params['lg']=isset($options['lg']) && $options['lg'] ?  $options['lg'] : ""; 
        
        $mandatory=isset($options['mandatory']) ? $options['mandatory'] : null; 
        $checkin=isset($options['checkin']) && $options['checkin'] ? $options['checkin'] : null;   
        $checkout=isset($options['checkout']) && $options['checkout'] ? $options['checkout'] : null;   
        
        // calculate night number
        $date_in_datetime=date_create($checkin);
        $date_out_datetime=date_create($checkout);        
        $night_number =(int)date_diff($date_in_datetime,$date_out_datetime)->format('%a');
        
        $accessories = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
        $accessoriesReturn=array();
        
        foreach($accessories as $accessory) {
            // ignore non public accessories
            if(isset($accessory['public']) && !(int)$accessory['public']) {
                continue;
            }
          
            if(isset($mandatory)) {
                // ignore accessories with mandatory distinct from the submitted option
                if($mandatory!=(int)$accessory['mandatory']) {
                    continue;
                }
                
                // if accessory is mandatory and checkin is not between dates, ignore
                if($accessory['mandatory']==1 && $checkin && $accessory['date_from'] && $checkin<$accessory['date_from']) {
                    continue;
                }
                
                if($accessory['mandatory']==1 && $checkin && $accessory['date_to'] && $checkin>$accessory['date_to']) {
                    continue;
                }
            }
            
            // calculate accessory price
            $totalprice=0;
            if($accessory['price_type']=='day') { $totalprice=$accessory['price']*$night_number; }
            else if($accessory['price_type']=='week') { $totalprice=($accessory['price']/7)*$night_number; }
            else { $totalprice=$accessory['price']; }
            $accessory['totalprice']=$totalprice;
            
            $accessoriesReturn[]=$accessory;
        }
        
        return $accessoriesReturn;
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
    
    public function searchBooking($bookingRef,$email)
    {
        $endPoint = $this->getEndPoint('booking_search');
        $booking=$this->api(sprintf($endPoint . '?ref_string=%s',$bookingRef));
        
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
            $puntualoffer['image']=(isset($puntualoffer['image_id'])) ? sprintf('%s/typologies/%s/images/%s/image.jpg?max_w=%s&max_h=%s&quality=%s',
                        $this->apiBaseUrl,
                        $puntualoffer['typology_id'],
                        $puntualoffer['image_id'],
                        $params['max_w'],
                        $params['max_h'],
                        $params['quality']
                        )
                : null;
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
}
