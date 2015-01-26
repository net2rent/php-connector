<?php
namespace Net2rent\Connector;

class Web extends AbstractConnector
{

    protected $_endPoints = array(
        'company' => '/companies/{{company}}',
        'agency' => '/agencies/',
        'cities' => '/companies/{{company}}/properties-cities',
        'properties' => '/companies/{{company}}/typologies',
        'property' => '/typologies/%s',
        'properties_availables' => '/companies/{{company}}/lists/typologies_availability',
        'property_available' => '/companies/{{company}}/lists/typologies_availability/%s',
        'property_images' => '/typologies/%s/images',
        'property_equipment' => '/properties/%s/equipment',        
        'property_propertystatus' => '/properties/%s/propertystatus',
        'property_availability' => '/typologies/%s/pricecalendar',
        'season_days' => '/seasons/%d/days',
        'contacts' => '/companies/{{company}}/contacts',
        'contact' => '/contacts/',
        'booking' => '/bookings/',
        'payment' => '/bookings/%s/payments/',
        'comments' => '/typologies/%s/clientcomments',
        'puntualoffers' => '/companies/{{company}}/puntualoffers',
        'properties_building_types' => '/companies/{{company}}/properties-building-types'
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
    
    /**
     * Gets property availability and prices
     *
     * @param  string  $property_id
     * @param  date  $options['from'] First date to get availability and prices. Format YYYY-MM-DD. Optional, if not set gets one year
     * @param  date  $options['to'] Last date to get availability and prices. Format YYYY-MM-DD. Optional, if not set gets one year
     * @return array  (items)
     */
    public function getAvailability($property_id, array $options = array())
    {
        $endPoint = $this->getEndPoint('property_availability', array(
            $property_id
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
        
        $seasonsRates=array();
        
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
                        $i++;
                        $seasonId=$day['season_id'];
                        $params_season['season_id']=$seasonId;

                        $endPointSeason=sprintf('%s/typologies/%s/season_price', 
                            $this->apiBaseUrl, 
                            $property['id']
                        );
                        $info=$this->api(sprintf($endPointSeason . '?%s', http_build_query($params_season)));
                        $seasonsRates[$i]=array('season_id'=>$seasonId, 'name'=>$info['season_name'], 'rentprice'=>$info['rentprice'], 'day_week'=>$info['day_week']);
                        $seasonsRates[$i]['dates'][$j]=array('start_day'=>$day['day']);
                        $prev=$day['day'];
                    }
                }
                $seasonsRates[$i]['dates'][$j]['end_day']=$prev;
            }
        }
                
        $endPoint2=sprintf('%s/typologies/%s/pricecalendar?overwritten=1', 
                        $this->apiBaseUrl, 
                        $property['id']
                );     
                
        // get days with price overwritten
        $overwrittenDays = $this->api(sprintf($endPoint2 . '%s', http_build_query($params)));
     
        $overwerittenRates=array();
        
        if($overwrittenDays)
        {
            $price = null;
            $i = -1;
            $j = 0;
            $prev = '';
            foreach($overwrittenDays as $day)
            {
                if($day['rentprice'] == $price)
                {
                    $next = date('Y-m-d', strtotime('+1 day', strtotime($prev)));
                    if($day['day'] != $next)
                    {
                        $overwerittenRates[$i]['dates'][$j]['end_day'] = $prev;
                        $j++;
                        $overwerittenRates[$i]['dates'][$j]['start_day'] = $day['day'];
                    }
                    $prev = $day['day'];
                }
                else
                {
                    if($i > -1)
                    {
                        $overwerittenRates[$i]['dates'][$j]['end_day'] = $prev;
                    }
                    $j = 0;
                    $i++; 
                    $price = $day['rentprice'];
                    
                    $overwerittenRates[$i] = array('rentprice' => $price,'name'=>'','season_id'=>0,'day_week'=>'day');
                    $overwerittenRates[$i]['dates'][$j] = array('start_day' => $day['day']);
                    $prev = $day['day'];
                }
            }
            $overwerittenRates[$i]['dates'][$j]['end_day'] = $prev;
        }
        
        $seasonsOverwrittenRates=array_merge($seasonsRates,$overwerittenRates);
                
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
    
    public function insertBooking(array $bookingOptions)
    {
        $endPoint = $this->getEndPoint('booking');
        return $this->api($endPoint, 'POST', $bookingOptions);
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
     * @param  string $options['orderby'] Order comments
     * @return array  (total|items)
     */
    public function getComments($propertyId, array $options = array())
    {
        $endPoint = $this->getEndPoint('comments');

        $params = array();
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
}
