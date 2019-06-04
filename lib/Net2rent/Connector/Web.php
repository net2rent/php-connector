<?php

namespace Net2rent\Connector;

class Web extends AbstractConnector {

  protected $_endPoints = array(
    'company' => '/companies/{{company}}',
    'agency' => '/agencies/',
    'agencies' => '/companies/{{company}}/buildings/%s/agencies',
    'cities' => '/companies/{{company}}/properties-cities',
    'buildings' => '/companies/{{company}}/nosinglebuildings',
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
    'booking_accessories' => '/bookings/%s/accessories',
    'payments' => '/bookings/%s/payments',
    'payment' => '/bookings/%s/payments/',
    'comments' => '/typologies/%s/clientcomments',
    'puntualoffers' => '/companies/{{company}}/puntualoffers',
    'puntualoffers_property' => '/typologies/%s/puntualoffers',
    'puntualoffer_property' => '/typologies/%s/puntualoffer',
    'puntualoffer' => '/promotions/puntualoffers/',
    'discounts' => '/companies/{{company}}/discounts',
    'discounts_property' => '/typologies/%s/discounts',
    'properties_building_types' => '/companies/{{company}}/properties-building-types',
    'properties_zones' => '/companies/{{company}}/properties-zones',
    'hear_about_us' => '/enums/14/values',
    'companies_minimum_nights' => '/companies/{{company}}/baseprices/minimum_nights',
    'companies_entry_days' => '/companies/{{company}}/baseprices/entry_days',
    'companies_out_days' => '/companies/{{company}}/baseprices/out_days',
    'packages' => '/companies/{{company}}/packages',
    'package' => '/packages/%s',
    'package_available_properties' => '/packages/%s/availabletypologies',
    'ecommerce_categories' => '/companies/{{company}}/ecommercecategories',
    'ecommerce_products' => '/companies/{{company}}/ecommerceproducts',
    'ecommerce_product' => '/ecommerce/product/%s',
    'ecommerce_product_days' => '/ecommerce/product/%s/days',
    'ecommerce_sale' => '/ecommerce/sale/',
    'ecommerce_sale_price' => '/ecommerce/sale/price',
    'booking_ecommercesales' => '/bookings/%s/ecommercesales',
    'ecommerce_sale_payments' => '/ecommerce/sale/%s/payments/',
    'promotional_code' => '/promotions/promotionalcodes/%s/availability',
    'send_email' => '/companies/{{company}}/emails/'
  );

  public function getCompany() {
    $endPoint = $this->getEndPoint('company');
    return $this->api($endPoint);
  }

  public function getAgency($agencyId) {
    $endPoint = $this->getEndPoint('agency');
    return $this->api(sprintf($endPoint . '%s', $agencyId));
  }

  public function getAgenciesProperty($buildingId) {
    $endPoint = $this->getEndPoint('agencies');
    return $this->api(sprintf($endPoint, $buildingId));
  }

  /**
   * Gets no single buildings from a company
   *
   * @return array  (items)
   */
  public function getBuildings() {
    $endPoint = $this->getEndPoint('buildings');

    $buildings = $this->api($endPoint);

    return array(
      'total' => count($buildings),
      'items' => $buildings
    );
  }

  /**
   * Gets property availability and prices
   *
   * @param  string  $propertyId
   * @param  date  $options['from'] First date to get availability and prices. Format YYYY-MM-DD. Optional, if not set gets one year
   * @param  date  $options['to'] Last date to get availability and prices. Format YYYY-MM-DD. Optional, if not set gets one year
   * @return array  (items)
   */
  public function getAvailability($propertyId, array $options = array()) {
    $endPoint = $this->getEndPoint('property_availability', array(
      $propertyId
    ));

    $property_id = 0;

    $params = array();
    $params['from'] = date('Y-m-d');
    $params['to'] = date('Y-m-d', strtotime(date('Y-m-d') . ' + 1 year'));
    if (isset($options['from'])) {
      $params['from'] = $this->checkDateFormat($options['from']);
    }
    if (isset($options['to'])) {
      $params['to'] = $this->checkDateFormat($options['to']);
    }
    if (isset($options['property_id']) && $options['property_id']) {
      $property_id = $options['property_id'];
    }

    $baseprices = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
    $a_propertystatus = array();
    $propertyStatusDayIndexed = array();
    if ($property_id) {
      $endPointPropertyStatus = '/properties/' . $property_id . '/propertystatus';
      $a_propertystatus = $this->api($endPointPropertyStatus);
      foreach ($a_propertystatus as $propertystatus) {
        $propertyStatusDayIndexed[$propertystatus['day']] = $propertystatus;
      }
    }

    $availabilityPricesDays = array();

    // Get only necessary fields
    foreach ($baseprices as $baseprice) {
      $availabilityPriceDay['day'] = $baseprice['day']; // day
      $availabilityPriceDay['rentprice'] = $baseprice['rentprice']; // rent price for this day
      $availabilityPriceDay['norefoundprice'] = $baseprice['norefoundprice']; // no refund price for this day
      $availabilityPriceDay['discountprice'] = $baseprice['discountprice']; // discount price for this day
      $availabilityPriceDay['minimum_nights'] = $baseprice['minimum_nights']; // minimum booking nights if is this entry day
      $availabilityPriceDay['checkin'] = $baseprice['checkin']; // 1 if possible checkin this day, 0 if not
      $availabilityPriceDay['checkout'] = $baseprice['checkout']; // 1 if possible checkout this day, 0 if not
      $availabilityPriceDay['available'] = (float) $baseprice['rentprice'] > 0 && (int) $baseprice['available'] > 0 ? 1 : 0; // available: 1=yes, 0=no
      $availabilityPriceDay['not_available'] = !isset($propertyStatusDayIndexed[$baseprice['day']]) || $propertyStatusDayIndexed[$baseprice['day']]['status'] == 'not_available' ? 1 : 0;
      $availabilityPricesDays[] = $availabilityPriceDay;
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
  public function getRates(array $property, array $options = array()) {

    $params = array();
    if (isset($options['from'])) {
      $params['from'] = $this->checkDateFormat($options['from']);
    }
    if (isset($options['to'])) {
      $params['to'] = $this->checkDateFormat($options['to']);
    }
    if (isset($options['overwritten_priority'])) {
      $params['overwritten_priority'] = (int) $options['overwritten_priority'];
    }
    else {
      $params['overwritten_priority'] = 0;
    }
    if (isset($options['only_contract'])) {
      $params['only_contract'] = (int) $options['only_contract'];
    }
    else {
      $params['only_contract'] = 0;
    }
    $params['orderby'] = 'seasondate.day';

    $seasonsRates = array();

    // if is set only_contract, get contract days
    $contractDaysIndexed = array();
    if ($params['only_contract']) {
      $endPoint = sprintf('%s/properties/%s/propertystatus', $this->apiBaseUrl, $property['first_property_id']
      );

      // get propertystatus to obtain contract days
      $contractDays = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));

      // index propertystatus by day
      foreach ($contractDays as $contractDay) {
        $contractDaysIndexed[$contractDay['day']] = $contractDay;
      }
    }

    // get days with price overwritten
    $endPoint2 = sprintf('%s/typologies/%s/pricecalendar?overwritten=1', $this->apiBaseUrl, $property['id']
    );
    $overwrittenDays = $this->api(sprintf($endPoint2 . '%s', http_build_query($params)));
    $overwrittenDaysIndexed = array();
    // index overwritten days by day
    foreach ($overwrittenDays as $overwrittenDay) {
      $overwrittenDaysIndexed[$overwrittenDay['day']] = $overwrittenDay;
    }

    // get baseprices
    $endPoint3 = sprintf('%s/typologies/%s/pricecalendar?', $this->apiBaseUrl, $property['id']
    );
    $basePrices = $this->api(sprintf($endPoint3 . '%s', http_build_query($params)));
    $basePricesDaysIndexed = array();
    // index overwritten days by day
    foreach ($basePrices as $basePrice) {
      $basePricesDaysIndexed[$basePrice['day']] = $basePrice;
    }

    if (isset($property['agency_id']) && $property['agency_id']) {

      $endPoint = sprintf('%s/agencies/%s/seasons/calendar', $this->apiBaseUrl, $property['agency_id']
      );

      // get season days
      $seasonDays = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));

      $params_season = array();
      $params_season['lg'] = $options['lg'];
      if ($seasonDays) {
        $seasonId = 0;
        $i = -1;
        $j = 0;
        $prev = '';
        foreach ($seasonDays as $day) {
          $minimum_nights = isset($basePricesDaysIndexed[$day['day']]) ? $basePricesDaysIndexed[$day['day']]['minimum_nights'] : null;

          if (
            (!$params['only_contract'] ||
            ($params['only_contract'] && isset($contractDaysIndexed[$day['day']]) && (int) $contractDaysIndexed[$day['day']]['contract'])) && !isset($overwrittenDaysIndexed[$day['day']])
          ) {
            if ($day['season_id'] == $seasonId && $minimum_nights == $seasonsRates[$i]['dates'][$j]['minimum_nights']) {
              $next = date('Y-m-d', strtotime('+1 day', strtotime($prev)));
              if ($day['day'] != $next) {
                $seasonsRates[$i]['dates'][$j]['end_day'] = $prev;
                $j++;
                $seasonsRates[$i]['dates'][$j]['start_day'] = $day['day'];
                $seasonsRates[$i]['dates'][$j]['minimum_nights'] = $minimum_nights;
              }
              $prev = $day['day'];
            }
            else {
              if ($i > -1) {
                $seasonsRates[$i]['dates'][$j]['end_day'] = $prev;
              }
              $j = 0;
              $seasonId = $day['season_id'];
              $i = $seasonId;
              $params_season['season_id'] = $seasonId;

              // if season is created in dates before, set index for new period
              if (isset($seasonsRates[$i])) {
                foreach ($seasonsRates[$i]['dates'] as $dates) {
                  if ($dates) {

                  }
                  $j++;
                }
              }

              $endPointSeason = sprintf('%s/typologies/%s/season_price', $this->apiBaseUrl, $property['id']
              );
              $info = $this->api(sprintf($endPointSeason . '?%s', http_build_query($params_season)));

              // create season only if was not created in early dates
              if ($j == 0) {
                $seasonsRates[$i] = array('season_id' => $seasonId, 'name' => $info['season_name'], 'rentprice' => $info['rentprice'], 'day_week' => $info['day_week'], 'minimum_nights' => $minimum_nights);
              }
              $seasonsRates[$i]['dates'][$j] = array('start_day' => $day['day'], 'minimum_nights' => $minimum_nights);
              $prev = $day['day'];
            }
          }
        }
        if ($prev) {
          $seasonsRates[$i]['dates'][$j]['end_day'] = $prev;
        }
      }
    }

    $overwerittenRates = array();

    if ($overwrittenDays) {
      $price = null;
      $i = -1;
      $j = 0;
      $prev = '';
      foreach ($overwrittenDays as $day) {
        $minimum_nights = isset($basePricesDaysIndexed[$day['day']]) ? $basePricesDaysIndexed[$day['day']]['minimum_nights'] : null;

        if (!$params['only_contract'] ||
          ($params['only_contract'] && isset($contractDaysIndexed[$day['day']]) && (int) $contractDaysIndexed[$day['day']]['contract'])) {
          if ($day['rentprice'] == $price && $minimum_nights == $overwerittenRates[$i]['dates'][$j]['minimum_nights']) {
            $next = date('Y-m-d', strtotime('+1 day', strtotime($prev)));
            if ($day['day'] != $next) {
              $overwerittenRates[$i]['dates'][$j]['end_day'] = $prev;
              $j++;
              $overwerittenRates[$i]['dates'][$j]['start_day'] = $day['day'];
              $overwerittenRates[$i]['dates'][$j]['minimum_nights'] = $minimum_nights;
            }
            $prev = $day['day'];
          }
          else {
            if ($i > -1) {
              $overwerittenRates[$i]['dates'][$j]['end_day'] = $prev;
            }
            $j = 0;
            $i++;
            $price = $day['rentprice'];

            $overwerittenRates[$i] = array('rentprice' => $price, 'name' => '', 'season_id' => 0, 'day_week' => 'day', 'minimum_nights' => $day['minimum_nights']);
            $overwerittenRates[$i]['dates'][$j] = array('start_day' => $day['day'], 'minimum_nights' => $minimum_nights);
            $prev = $day['day'];
          }
        }
      }
      $overwerittenRates[$i]['dates'][$j]['end_day'] = $prev;
    }

    if ($params['overwritten_priority'] && !empty($overwerittenRates)) {
      $seasonsOverwrittenRates = $overwerittenRates;
    }
    else {
      $seasonsOverwrittenRates = array_merge($seasonsRates, $overwerittenRates);
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
  public function getContacts(array $options = array()) {
    $endPoint = $this->getEndPoint('contacts');

    $params = array();
    $params['active'] = 1;
    if (isset($options['email'])) {
      $params['email'] = $options['email'];
    }
    $contacts = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));

    return array(
      'items' => $contacts
    );
  }

  public function getContact($contactId) {
    $endPoint = $this->getEndPoint('contact');
    return $this->api(sprintf($endPoint . '%s', $contactId));
  }

  public function insertContact(array $contactOptions) {
    $endPoint = $this->getEndPoint('contact');
    return $this->api($endPoint, 'POST', $contactOptions);
  }

  public function updateContact($contactId, array $contactOptions) {
    $endPoint = $this->getEndPoint('contact');
    $this->api(sprintf($endPoint . '%s', $contactId), 'PUT', $contactOptions);
  }

  public function getBooking($bookingId) {
    $endPoint = $this->getEndPoint('booking');
    return $this->api(sprintf($endPoint . '%s', $bookingId));
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
  public function getBookingsContact($contactId, array $options = array()) {
    $params = array();
    $params['offset'] = 0;
    if (isset($options['offset'])) {
      $params['offset'] = $options['offset'];
    }
    $params['limit'] = -1;
    if (isset($options['limit'])) {
      $params['limit'] = $options['limit'];
    }
    $params['status'] = '';
    if (isset($options['status'])) {
      $params['status'] = $options['status'];
    }
    $params['date_type'] = 0;
    if (isset($options['date_type'])) {
      $params['date_type'] = $options['date_type'];
    }
    $params['date_in'] = '';
    if (isset($options['date_in'])) {
      $params['date_in'] = $options['date_in'];
    }
    $params['orderby'] = 'offer.date_in';
    if (isset($options['orderby'])) {
      $params['orderby'] = $options['orderby'];
    }
    $params['orderdesc'] = 0;
    if (isset($options['orderdesc'])) {
      $params['orderdesc'] = $options['orderdesc'];
    }

    $endPoint = $this->getEndPoint('bookings_contact');
    $bookings = $this->api(sprintf($endPoint . '?%s', $contactId, http_build_query($params)));

    return array(
      'items' => $bookings,
      'total' => count($bookings)
    );
  }

  public function getGuaranteedBookingsBooking($bookingId) {
    $endPoint = $this->getEndPoint('bookings_guaranteed');
    $bookings = $this->api(sprintf($endPoint, $bookingId));

    return array(
      'items' => $bookings,
      'total' => count($bookings)
    );
  }

  public function searchBooking($bookingRef, $email) {
    $endPoint = $this->getEndPoint('booking_search');
    $booking = $this->api(sprintf($endPoint . '?ref_string=%s', urlencode($bookingRef)));

    // only return booking if email matches
    if ($booking && !empty($booking) && $booking[0]['contact_email'] == $email) {
      return $booking[0];
    }
    else {
      return null;
    }
  }

  /**
   * Inserts a booking. To get fields, consult online documentation at
   * https://hub.net2rent.com/doc/employee.php?action=show_form&filteru=&apiurl=hub.net2rent.com&usr=admin%40company.com&pas=admin_company&section=bookings&call=POST+%2Fbookings%2F
   */
  public function insertBooking(array $bookingOptions) {
    $endPoint = $this->getEndPoint('booking');
    return $this->api($endPoint, 'POST', $bookingOptions);
  }

  /**
   * Modifies a booking. To get fields, consult online documentation at
   * https://hub.net2rent.com/doc/employee.php?action=show_form&filteru=&apiurl=hub.net2rent.com&usr=admin%40company.com&pas=admin_company&section=bookings&call=PUT+%2Fbookings%2F%3Abooking_id
   */
  public function modifyBooking($bookingId, array $bookingOptions) {
    $endPoint = $this->getEndPoint('booking');
    return $this->api(sprintf($endPoint . '/%s', $bookingId), 'PUT', $bookingOptions);
  }

  /**
   * Inserts a booking person. To get fields, consult online documentation at
   * https://hub.net2rent.com/doc/employee.php?action=show_form&filteru=&apiurl=hub.net2rent.com&usr=admin%40company.com&pas=admin_company&section=bookings&call=POST+%2Fbookings%2F%3Abooking_id%2Fpeople%2F
   */
  public function insertBookingPerson(array $bookingPersonOptions) {
    $endPoint = $this->getEndPoint('booking_person');
    return $this->api(sprintf($endPoint, $bookingPersonOptions['booking_id']), 'POST', $bookingPersonOptions);
  }

  /**
   * Inserts a booking accessory. To get fields, consult online documentation at
   * https://hub.net2rent.com/doc/employee.php?action=show_form&filteru=&apiurl=hub.net2rent.com&usr=admin%40company.com&pas=admin_company&section=bookings&call=POST+%2Fbookings%2F%3Abooking_id%2Faccessories%2F
   */
  public function insertBookingAccessory(array $bookingAccessoryOptions) {
    $endPoint = $this->getEndPoint('booking_accessory');
    return $this->api(sprintf($endPoint, $bookingAccessoryOptions['booking_id']), 'POST', $bookingAccessoryOptions);
  }

  public function getBookingAccessories($bookingId, $lang = null) {
    $endPoint = $this->getEndPoint('booking_accessories');
    $accessories = $this->api(sprintf($endPoint . '?lang=%s', $bookingId, $lang), 'GET');

    return array(
      'total' => count($accessories),
      'items' => $accessories
    );
  }

  public function getPayments($bookingId) {
    $endPoint = $this->getEndPoint('payments');
    $payments = $this->api(sprintf($endPoint, $bookingId), 'GET');

    return array(
      'total' => count($payments),
      'items' => $payments
    );
  }

  public function getPayment($bookingId, $paymentId) {
    $endPoint = $this->getEndPoint('payment');
    return $this->api(sprintf($endPoint . '?%s', $bookingId, $paymentId), 'GET');
  }

  public function insertPayment($bookingId, array $paymentOptions) {
    $endPoint = $this->getEndPoint('payment');
    return $this->api(sprintf($endPoint, $bookingId), 'POST', $paymentOptions);
  }

  public function cancelBooking($bookingId, array $bookingOptions) {
    $endPoint = $this->getEndPoint('booking');
    return $this->api(sprintf($endPoint . '%s', $bookingId), 'PUT', $bookingOptions);
  }

  /**
   * Gets comments from a property
   *
   * @param  string $options['public'] Filter by public. 1=public, 0=no public, null=all (default=1)
   * @param  string $options['lang'] Filter by comment language, valid values: ca,es,en,fr,de,nl,it,ru
   * @param  string $options['orderby'] Order comments field
   * @param  string $options['orderdesc'] Order comments ASC (0) or DESC (1)
   * @return array  (total|items)
   */
  public function getComments($propertyId, array $options = array()) {
    $endPoint = $this->getEndPoint('comments');

    $params = array();
    $params['public'] = 1;
    if (isset($options['public'])) {
      $params['public'] = (int) $options['public'];
    }
    $params['orderby'] = 'clientcomment.creation_date';
    if (isset($options['orderby'])) {
      $params['orderby'] = $options['orderby'];
    }
    $params['orderdesc'] = 1;
    if (isset($options['orderdesc'])) {
      $params['orderdesc'] = $options['orderdesc'];
    }

    $comments = $this->api(sprintf($endPoint . '?%s', $propertyId, http_build_query($params)));
    $commentsTotal = $this->api(sprintf($endPoint . '/size?%s', $propertyId, http_build_query($params)));

    return array(
      'total' => $commentsTotal['size'],
      'items' => $comments
    );
  }

  public function insertComment($propertyId, array $commentOptions) {
    $endPoint = $this->getEndPoint('comments');
    return $this->api(sprintf($endPoint, $propertyId) . '/', 'POST', $commentOptions);
  }

  /**
   * Gets putualoffers from a company
   *
   * @param  integer  $options['page_number'] Page number
   * @param  integer  $options['page_size'] Page size
   * @param  bool $options['active'] Filter by active
   * @param  bool $options['unexpired'] Filter by unexpired
   * @param  integer $options['typology_id'] Filter by typology_id
   * @param  string $options['start_day_from'] Filter by start day from
   * @param  string $options['start_day_to'] Filter by start day to
   * @param  string $options['search'] Filter by name, zone or city with LIKE %xxx%
   * @param  string $options['city'] Filter by city name
   * @param  string $options['building_type'] Filter by building_type
   * @param  string $options['min_capacity'] Filter by capacity from
   * @param  integer $options['limit'] Limit results
   * @param  string $options['orderby'] Order comments field
   * @param  string $options['orderdesc'] Order comments ASC (0) or DESC (1)
   * @param  integer  $options['max_w'] Max width of image
   * @param  integer  $options['max_h'] Max height of image
   * @param  integer  $options['quality'] JPEG quality percent of image
   * @return array  (total|items)
   */
  public function getPuntualoffers(array $options = array()) {
    $endPoint = $this->getEndPoint('puntualoffers');

    $params = array();
    if (isset($options['page_number'])) {
      $limit = (isset($options['page_size'])) ? $options['page_size'] : 10;
      $params['limit'] = (int) $limit;
      $params['offset'] = ($options['page_number'] - 1) * $limit;
    }
    $params['active'] = 1;
    if (isset($options['active'])) {
      $params['active'] = $options['active'];
    }
    $params['unexpired'] = 1;
    if (isset($options['unexpired'])) {
      $params['unexpired'] = $options['unexpired'];
    }
    $params['typology_id'] = null;
    if (isset($options['typology_id'])) {
      $params['typology_id'] = $options['typology_id'];
    }
    $params['start_day_from'] = null;
    if (isset($options['start_day_from'])) {
      $params['start_day_from'] = $options['start_day_from'];
    }
    $params['start_day_to'] = null;
    if (isset($options['start_day_to'])) {
      $params['start_day_to'] = $options['start_day_to'];
    }
    $params['only_available'] = 1;
    if (isset($options['only_available'])) {
      $params['only_available'] = $options['only_available'];
    }
    $params['search'] = null;
    if (isset($options['search'])) {
      $params['search'] = $options['search'];
    }
    $params['city'] = null;
    if (isset($options['city'])) {
      $params['city'] = $options['city'];
    }
    $params['building_type'] = null;
    if (isset($options['building_type'])) {
      $params['building_type'] = $options['building_type'];
    }
    $params['min_capacity'] = null;
    if (isset($options['min_capacity'])) {
      $params['min_capacity'] = $options['min_capacity'];
    }
    $params['orderby'] = 'puntualoffer.start_day';
    if (isset($options['orderby'])) {
      $params['orderby'] = $options['orderby'];
    }
    $params['orderdesc'] = 0;
    if (isset($options['orderdesc'])) {
      $params['orderdesc'] = $options['orderdesc'];
    }
    $params['max_w'] = 4000;
    if (isset($options['max_w'])) {
      $params['max_w'] = $options['max_w'];
    }
    $params['max_h'] = 3000;
    if (isset($options['max_h'])) {
      $params['max_h'] = $options['max_h'];
    }
    $params['quality'] = 80;
    if (isset($options['quality'])) {
      $params['quality'] = $options['quality'];
    }

    $puntualoffers = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
    $puntualoffersTotal = $this->api(sprintf($endPoint . '/size?%s', http_build_query($params)));

    $puntualoffers_with_image = array();
    foreach ($puntualoffers as $puntualoffer) {
      $puntualoffer['name_lg'] = array(
        'es' => strip_tags($puntualoffer['name_es']),
        'ca' => strip_tags($puntualoffer['name_ca']),
        'en' => strip_tags($puntualoffer['name_en']),
        'fr' => strip_tags($puntualoffer['name_fr']),
        'de' => strip_tags($puntualoffer['name_de']),
        'nl' => strip_tags($puntualoffer['name_nl']),
        'it' => strip_tags($puntualoffer['name_it']),
        'ru' => strip_tags($puntualoffer['name_ru'])
      );
      $puntualoffer['subtitle_lg'] = array(
        'es' => strip_tags($puntualoffer['subtitle_es']),
        'ca' => strip_tags($puntualoffer['subtitle_ca']),
        'en' => strip_tags($puntualoffer['subtitle_en']),
        'fr' => strip_tags($puntualoffer['subtitle_fr']),
        'de' => strip_tags($puntualoffer['subtitle_de']),
        'nl' => strip_tags($puntualoffer['subtitle_nl']),
        'it' => strip_tags($puntualoffer['subtitle_it']),
        'ru' => strip_tags($puntualoffer['subtitle_ru'])
      );
      $puntualoffer['text_lg'] = array(
        'es' => strip_tags($puntualoffer['text_es']),
        'ca' => strip_tags($puntualoffer['text_ca']),
        'en' => strip_tags($puntualoffer['text_en']),
        'fr' => strip_tags($puntualoffer['text_fr']),
        'de' => strip_tags($puntualoffer['text_de']),
        'nl' => strip_tags($puntualoffer['text_nl']),
        'it' => strip_tags($puntualoffer['text_it']),
        'ru' => strip_tags($puntualoffer['text_ru'])
      );

      $puntualoffer['image'] = sprintf('%s/promotions/puntualoffers/%s/image?max_w=%s&max_h=%s&quality=%s', $this->apiBaseUrl, $puntualoffer['id'], $params['max_w'], $params['max_h'], $params['quality']
      );

      // if puntualoffer has no image, get image from typology
      $file_headers = get_headers($puntualoffer['image']);

      if ($file_headers[0] != 'HTTP/1.1 200 OK') {
        $puntualoffer['image'] = (isset($puntualoffer['image_id'])) ? sprintf('%s/typologies/%s/images/%s/image.jpg?max_w=%s&max_h=%s&quality=%s', $this->apiBaseUrl, $puntualoffer['typology_id'], $puntualoffer['image_id'], $params['max_w'], $params['max_h'], $params['quality']
          ) : null;
      }

      $puntualoffers_with_image[] = $puntualoffer;
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
  public function getPuntualoffersProperty($propertyId, array $options = array()) {
    $endPoint = $this->getEndPoint('puntualoffers_property');

    $params = array();
    $params['active'] = 1;
    if (isset($options['active'])) {
      $params['active'] = $options['active'];
    }
    $params['unexpired'] = 1;
    if (isset($options['unexpired'])) {
      $params['unexpired'] = $options['unexpired'];
    }
    $params['start_day'] = '';
    if (isset($options['start_day'])) {
      $params['start_day'] = $options['start_day'];
    }
    $params['end_day'] = '';
    if (isset($options['end_day'])) {
      $params['end_day'] = $options['end_day'];
    }

    $puntualoffers = $this->api(sprintf($endPoint . '?%s', $propertyId, http_build_query($params)));


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
  public function getPuntualofferProperty($propertyId, array $options = array()) {
    $endPoint = $this->getEndPoint('puntualoffer_property');

    $params = array();
    $params['active'] = 1;
    if (isset($options['active'])) {
      $params['active'] = $options['active'];
    }
    $params['start_day'] = '';
    if (isset($options['start_day'])) {
      $params['start_day'] = $options['start_day'];
    }
    $params['end_day'] = '';
    if (isset($options['end_day'])) {
      $params['end_day'] = $options['end_day'];
    }

    $puntualoffer = $this->api(sprintf($endPoint . '?%s', $propertyId, http_build_query($params)));

    return $puntualoffer;
  }

  public function getPuntualOffer($puntualOfferId) {
    $endPoint = $this->getEndPoint('puntualoffer');
    return $this->api(sprintf($endPoint . '/%s', $puntualOfferId), 'GET');
  }

  /**
   * Modifies puntualoffer.To get fields, consult online documentation at
   * https://hub.net2rent.com/doc/employee.php?action=show_form&filteru=&apiurl=hub.net2rent.com&usr=admin%40company.com&pas=admin_company&section=promotions&call=PUT+%2Fpromotions%2Fpuntualoffers%2F%3Aid
   *
   * @param  bool $options['active'] Filter by active. Values: 1=yes, 0=no
   * @return void
   */
  public function modifyPuntualOffer($puntualOfferId, array $options = array()) {
    $endPoint = $this->getEndPoint('puntualoffer');
    return $this->api(sprintf($endPoint . '/%s', $puntualOfferId), 'PUT', $options);
  }

  /**
   * Gets discounts
   *
   * @param  bool $options['active'] Filter by active
   * @param  bool $options['unexpired'] Filter by unexpired
   * @param  string $options['orderby'] Order discounts
   * @param  string $options['orderdesc'] Order discounts ASC (0) or DESC (1)
   * @return array  (total|items)
   */
  public function getDiscounts(array $options = array()) {
    $endPoint = $this->getEndPoint('discounts');

    $params = array();
    $params['active'] = 1;
    if (isset($options['active'])) {
      $params['active'] = $options['active'];
    }
    $params['unexpired'] = 1;
    if (isset($options['unexpired'])) {
      $params['unexpired'] = $options['unexpired'];
    }
    $params['orderby'] = 'discount.end_day';
    if (isset($options['orderby'])) {
      $params['orderby'] = $options['orderby'];
    }
    $params['orderdesc'] = 0;
    if (isset($options['orderdesc'])) {
      $params['orderdesc'] = $options['orderdesc'];
    }

    $params['limit'] = 200;
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
  public function getDiscountsProperty($propertyId, array $options = array()) {
    $endPoint = $this->getEndPoint('discounts_property');

    $params = array();
    $params['active'] = 1;
    if (isset($options['active'])) {
      $params['active'] = $options['active'];
    }
    $params['unexpired'] = 1;
    if (isset($options['unexpired'])) {
      $params['unexpired'] = $options['unexpired'];
    }
    $params['start_day'] = '';
    if (isset($options['start_day'])) {
      $params['start_day'] = $options['start_day'];
    }
    $params['end_day'] = '';
    if (isset($options['end_day'])) {
      $params['end_day'] = $options['end_day'];
    }
    $params['apply_final'] = true;
    if (isset($options['apply_final'])) {
      $params['apply_final'] = $options['apply_final'];
    }
    $params['apply_ttoo'] = false;
    if (isset($options['apply_ttoo'])) {
      $params['apply_ttoo'] = $options['apply_ttoo'];
    }
    $params['apply_central'] = false;
    if (isset($options['apply_central'])) {
      $params['apply_central'] = $options['apply_central'];
    }

    $discounts = $this->api(sprintf($endPoint . '?%s', $propertyId, http_build_query($params)));

    $discounts_filtered = array();
    foreach ($discounts as $discount) {
      if (
        ( (bool) $params['apply_final'] === true && $discount['apply_final'] ) || ( (bool) $params['apply_ttoo'] === true && $discount['apply_ttoo'] ) || ( (bool) $params['apply_central'] === true && $discount['apply_central'] )
      ) {
        $discounts_filtered[] = $discount;
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
  public function getPropertiesBuildingTypes(array $options = array()) {
    $endPoint = $this->getEndPoint('properties_building_types');

    $params = array();
    if (isset($options['commercialization_type'])) {
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
  public function getZones(array $options = array()) {
    $endPoint = $this->getEndPoint('properties_zones');

    $params = array();
    if (isset($options['city'])) {
      $params['city'] = $options['city'];
    }
    if (isset($options['commercialization_type'])) {
      $params['commercialization_type'] = $options['commercialization_type'];
    }
    return $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
  }

  public function getHearAboutUsValues() {
    $endPoint = $this->getEndPoint('hear_about_us');
    return $this->api($endPoint);
  }

  public function getMinimumNights() {
    $endPoint = $this->getEndPoint('companies_minimum_nights');
    $minimumNights = $this->api($endPoint);
    return isset($minimumNights['minimum_nights']) ? (int) $minimumNights['minimum_nights'] : 1;
  }

  public function getEntryDays() {
    $endPoint = $this->getEndPoint('companies_entry_days');
    $entryDays = $this->api($endPoint);
    return isset($entryDays['entry_days']) ? $entryDays['entry_days'] : array();
  }

  public function getOutDays() {
    $endPoint = $this->getEndPoint('companies_out_days');
    $outDays = $this->api($endPoint);
    return isset($outDays['out_days']) ? $outDays['out_days'] : array();
  }

  /**
   * Gets packages
   *
   * @param  integer $options['offset']
   * @param  integer $options['limit']
   * @param  string $options['orderby'] Order packages field
   * @param  integer $options['orderdesc'] Order packages ASC (0) or DESC (1)
   * @param  integer $options['people'] Filter by people number
   * @param  bool $options['active'] Filter by active
   * @param  integer  $options['max_w'] Max width of image
   * @param  integer  $options['max_h'] Max height of image
   * @param  integer  $options['quality'] JPEG quality percent of image
   * @return array  (total|items)
   */
  public function getPackages(array $options = array()) {
    $endPoint = $this->getEndPoint('packages');

    $params = array();
    $params['offset'] = 0;
    if (isset($options['offset'])) {
      $params['offset'] = $options['offset'];
    }
    $params['limit'] = -1;
    if (isset($options['limit'])) {
      $params['limit'] = $options['limit'];
    }
    $params['orderby'] = 'discount.end_day';
    if (isset($options['orderby'])) {
      $params['orderby'] = $options['orderby'];
    }
    $params['orderdesc'] = 0;
    if (isset($options['orderdesc'])) {
      $params['orderdesc'] = $options['orderdesc'];
    }
    $params['people'] = null;
    if (isset($options['people'])) {
      $params['people'] = $options['people'];
    }
    $params['active'] = 1;
    if (isset($options['active'])) {
      $params['active'] = $options['active'];
    }
    $params['max_w'] = 4000;
    if (isset($options['max_w'])) {
      $params['max_w'] = $options['max_w'];
    }
    $params['max_h'] = 3000;
    if (isset($options['max_h'])) {
      $params['max_h'] = $options['max_h'];
    }
    $params['quality'] = 80;
    if (isset($options['quality'])) {
      $params['quality'] = $options['quality'];
    }
    $packages_api = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
    $packagesTotal = $this->api(sprintf($endPoint . '/size?%s', http_build_query($params)));

    $packages = array();
    foreach ($packages_api as $package) {
      $package['image'] = array();
      foreach ($this->array_languages as $language) {
        $package['image'][$language] = sprintf('%s/packages/%s/image?lg=%s&max_w=%s&max_h=%s&quality=%s', $this->apiBaseUrl, $package['id'], $language, $params['max_w'], $params['max_h'], $params['quality']
        );
      }
      $package['name'] = array(
        'es' => strip_tags($package['name_es']),
        'ca' => strip_tags($package['name_ca']),
        'en' => strip_tags($package['name_en']),
        'fr' => strip_tags($package['name_fr']),
        'de' => strip_tags($package['name_de']),
        'nl' => strip_tags($package['name_nl']),
        'it' => strip_tags($package['name_it']),
        'ru' => strip_tags($package['name_ru'])
      );

      $package['subtitle'] = array(
        'es' => strip_tags($package['subtitle_es']),
        'ca' => strip_tags($package['subtitle_ca']),
        'en' => strip_tags($package['subtitle_en']),
        'fr' => strip_tags($package['subtitle_fr']),
        'de' => strip_tags($package['subtitle_de']),
        'nl' => strip_tags($package['subtitle_nl']),
        'it' => strip_tags($package['subtitle_it']),
        'ru' => strip_tags($package['subtitle_ru'])
      );

      $package['text'] = array(
        'es' => strip_tags($package['text_es']),
        'ca' => strip_tags($package['text_ca']),
        'en' => strip_tags($package['text_en']),
        'fr' => strip_tags($package['text_fr']),
        'de' => strip_tags($package['text_de']),
        'nl' => strip_tags($package['text_nl']),
        'it' => strip_tags($package['text_it']),
        'ru' => strip_tags($package['text_ru'])
      );

      $package['price_from'] = array(
        'es' => strip_tags($package['price_from_es']),
        'ca' => strip_tags($package['price_from_ca']),
        'en' => strip_tags($package['price_from_en']),
        'fr' => strip_tags($package['price_from_fr']),
        'de' => strip_tags($package['price_from_de']),
        'nl' => strip_tags($package['price_from_nl']),
        'it' => strip_tags($package['price_from_it']),
        'ru' => strip_tags($package['price_from_ru'])
      );
      $packages[] = $package;
    }

    return array(
      'total' => $packagesTotal,
      'items' => $packages
    );
  }

  /**
   * Gets package
   * @param  integer $packageId ID of the package
   * @param  integer  $options['max_w'] Max width of image
   * @param  integer  $options['max_h'] Max height of image
   * @param  integer  $options['quality'] JPEG quality percent of image
   * @return array
   */
  public function getPackage($packageId, array $options = array()) {
    $endPoint = $this->getEndPoint('package');

    $package = $this->api(sprintf($endPoint, $packageId));

    $params = array();
    $params['max_w'] = 4000;
    if (isset($options['max_w'])) {
      $params['max_w'] = $options['max_w'];
    }
    $params['max_h'] = 3000;
    if (isset($options['max_h'])) {
      $params['max_h'] = $options['max_h'];
    }
    $params['quality'] = 80;
    if (isset($options['quality'])) {
      $params['quality'] = $options['quality'];
    }

    $agency = $this->getAgency($package['agency_id']);

    $package['image'] = array();
    foreach ($this->array_languages as $language) {
      $package['image'][$language] = sprintf('%s/packages/%s/image?lg=%s&max_w=%s&max_h=%s&quality=%s', $this->apiBaseUrl, $package['id'], $language, $params['max_w'], $params['max_h'], $params['quality']
      );
    }
    $package['name'] = array(
      'es' => strip_tags($package['name_es']),
      'ca' => strip_tags($package['name_ca']),
      'en' => strip_tags($package['name_en']),
      'fr' => strip_tags($package['name_fr']),
      'de' => strip_tags($package['name_de']),
      'nl' => strip_tags($package['name_nl']),
      'it' => strip_tags($package['name_it']),
      'ru' => strip_tags($package['name_ru'])
    );

    $package['subtitle'] = array(
      'es' => strip_tags($package['subtitle_es']),
      'ca' => strip_tags($package['subtitle_ca']),
      'en' => strip_tags($package['subtitle_en']),
      'fr' => strip_tags($package['subtitle_fr']),
      'de' => strip_tags($package['subtitle_de']),
      'nl' => strip_tags($package['subtitle_nl']),
      'it' => strip_tags($package['subtitle_it']),
      'ru' => strip_tags($package['subtitle_ru'])
    );

    $package['text'] = array(
      'es' => strip_tags($package['text_es']),
      'ca' => strip_tags($package['text_ca']),
      'en' => strip_tags($package['text_en']),
      'fr' => strip_tags($package['text_fr']),
      'de' => strip_tags($package['text_de']),
      'nl' => strip_tags($package['text_nl']),
      'it' => strip_tags($package['text_it']),
      'ru' => strip_tags($package['text_ru'])
    );

    $package['price_from'] = array(
      'es' => strip_tags($package['price_from_es']),
      'ca' => strip_tags($package['price_from_ca']),
      'en' => strip_tags($package['price_from_en']),
      'fr' => strip_tags($package['price_from_fr']),
      'de' => strip_tags($package['price_from_de']),
      'nl' => strip_tags($package['price_from_nl']),
      'it' => strip_tags($package['price_from_it']),
      'ru' => strip_tags($package['price_from_ru'])
    );

    $package['days'] = $this->api(sprintf('%s/packages/%s/days', $this->apiBaseUrl, $package['id']
      )
    );
    $package['time_in'] = $agency['time_in'];

    return $package;
  }

  /**
   * Gets package
   * @param  integer $packageId ID of the package
   * @param  string  $options['date_in'] Date of checkin
   * @return array(items|total)
   */
  public function getPackageAvailableProperties($packageId, array $options = array()) {
    $endPoint = $this->getEndPoint('package_available_properties');

    $params = array();

    $params['date_in'] = '';
    if (isset($options['date_in'])) {
      $params['date_in'] = $options['date_in'];
    }

    $properties = $this->api(sprintf($endPoint . '?%s', $packageId, http_build_query($params)));

    return array(
      'total' => count($properties),
      'items' => $properties
    );
  }

  /**
   * Gets ecommerce categories
   *
   * @param  string $options['offset']
   * @param  string $options['limit']
   * @param  string $options['orderby'] Order categories field
   * @param  string $options['orderdesc'] Order categories ASC (0) or DESC (1)
   * @return array  (total|items)
   */
  public function getEcommerceCategories(array $options = array()) {
    $endPoint = $this->getEndPoint('ecommerce_categories');

    $params = array();
    $params['offset'] = 0;
    if (isset($options['offset'])) {
      $params['offset'] = $options['offset'];
    }
    $params['limit'] = -1;
    if (isset($options['limit'])) {
      $params['limit'] = $options['limit'];
    }
    $params['orderby'] = 'discount.end_day';
    if (isset($options['orderby'])) {
      $params['orderby'] = $options['orderby'];
    }
    $params['orderdesc'] = 0;
    if (isset($options['orderdesc'])) {
      $params['orderdesc'] = $options['orderdesc'];
    }
    $categories_api = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
    $categoriesTotal = $this->api(sprintf($endPoint . '/size?%s', http_build_query($params)));

    $categories = array();
    foreach ($categories_api as $category) {
      $category['name'] = array(
        'es' => strip_tags($category['name_es']),
        'ca' => strip_tags($category['name_ca']),
        'en' => strip_tags($category['name_en']),
        'fr' => strip_tags($category['name_fr']),
        'de' => strip_tags($category['name_de']),
        'nl' => strip_tags($category['name_nl']),
        'it' => strip_tags($category['name_it']),
        'ru' => strip_tags($category['name_ru'])
      );

      $category['description'] = array(
        'es' => strip_tags($category['description_es']),
        'ca' => strip_tags($category['description_ca']),
        'en' => strip_tags($category['description_en']),
        'fr' => strip_tags($category['description_fr']),
        'de' => strip_tags($category['description_de']),
        'nl' => strip_tags($category['description_nl']),
        'it' => strip_tags($category['description_it']),
        'ru' => strip_tags($category['description_ru'])
      );
      $categories[] = $category;
    }

    return array(
      'total' => $categoriesTotal,
      'items' => $categories
    );
  }

  /**
   * Gets ecommerce products
   *
   * @param  string $options['offset']
   * @param  string $options['limit']
   * @param  string $options['orderby'] Order products field
   * @param  string $options['orderdesc'] Order products ASC (0) or DESC (1)
   * @param  bool $options['active'] Filter by active
   * @param  bool $options['public'] Filter by public
   * @param  bool $options['offer_as_package'] Filter by offer_as_package
   * @param  integer  $options['max_w'] Max width of image
   * @param  integer  $options['max_h'] Max height of image
   * @param  integer  $options['quality'] JPEG quality percent of image
   * @return array  (total|items)
   */
  public function getEcommerceProducts(array $options = array()) {
    $endPoint = $this->getEndPoint('ecommerce_products');

    $params = array();
    $params['offset'] = 0;
    if (isset($options['offset'])) {
      $params['offset'] = $options['offset'];
    }
    $params['limit'] = -1;
    if (isset($options['limit'])) {
      $params['limit'] = $options['limit'];
    }
    $params['orderby'] = 'discount.end_day';
    if (isset($options['orderby'])) {
      $params['orderby'] = $options['orderby'];
    }
    $params['orderdesc'] = 0;
    if (isset($options['orderdesc'])) {
      $params['orderdesc'] = $options['orderdesc'];
    }
    $params['active'] = 1;
    if (isset($options['active'])) {
      $params['active'] = $options['active'];
    }
    $params['public'] = 1;
    if (isset($options['public'])) {
      $params['public'] = $options['public'];
    }
    $params['offer_as_package'] = '';
    if (isset($options['offer_as_package'])) {
      $params['offer_as_package'] = $options['offer_as_package'];
    }
    $params['max_w'] = 4000;
    if (isset($options['max_w'])) {
      $params['max_w'] = $options['max_w'];
    }
    $params['max_h'] = 3000;
    if (isset($options['max_h'])) {
      $params['max_h'] = $options['max_h'];
    }
    $params['quality'] = 80;
    if (isset($options['quality'])) {
      $params['quality'] = $options['quality'];
    }

    $products_api = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
    $productsTotal = $this->api(sprintf($endPoint . '/size?%s', http_build_query($params)));

    $products = array();
    foreach ($products_api as $product) {
      $product['image'] = sprintf('%s/ecommerce/product/%s/image?max_w=%s&max_h=%s&quality=%s', $this->apiBaseUrl, $product['id'], $params['max_w'], $params['max_h'], $params['quality']
      );

      $product['name'] = array(
        'es' => strip_tags($product['name_es']),
        'ca' => strip_tags($product['name_ca']),
        'en' => strip_tags($product['name_en']),
        'fr' => strip_tags($product['name_fr']),
        'de' => strip_tags($product['name_de']),
        'nl' => strip_tags($product['name_nl']),
        'it' => strip_tags($product['name_it']),
        'ru' => strip_tags($product['name_ru'])
      );

      $product['description'] = array(
        'es' => strip_tags($product['description_es']),
        'ca' => strip_tags($product['description_ca']),
        'en' => strip_tags($product['description_en']),
        'fr' => strip_tags($product['description_fr']),
        'de' => strip_tags($product['description_de']),
        'nl' => strip_tags($product['description_nl']),
        'it' => strip_tags($product['description_it']),
        'ru' => strip_tags($product['description_ru'])
      );

      $product['category_name'] = array(
        'es' => strip_tags($product['category_name_es']),
        'ca' => strip_tags($product['category_name_ca']),
        'en' => strip_tags($product['category_name_en']),
        'fr' => strip_tags($product['category_name_fr']),
        'de' => strip_tags($product['category_name_de']),
        'nl' => strip_tags($product['category_name_nl']),
        'it' => strip_tags($product['category_name_it']),
        'ru' => strip_tags($product['category_name_ru'])
      );
      $products[] = $product;
    }

    return array(
      'total' => $productsTotal,
      'items' => $products
    );
  }

  /**
   * Gets ecommerce product
   *
   * @param  integer  $productId ecommerce product id
   * @param  integer  $options['max_w'] Max width of image
   * @param  integer  $options['max_h'] Max height of image
   * @param  integer  $options['quality'] JPEG quality percent of image
   * @return array
   */
  public function getEcommerceProduct($productId, array $options = array()) {
    $endPoint = $this->getEndPoint('ecommerce_product');

    $params = array();
    $params['max_w'] = 4000;
    if (isset($options['max_w'])) {
      $params['max_w'] = $options['max_w'];
    }
    $params['max_h'] = 3000;
    if (isset($options['max_h'])) {
      $params['max_h'] = $options['max_h'];
    }
    $params['quality'] = 80;
    if (isset($options['quality'])) {
      $params['quality'] = $options['quality'];
    }

    $product = $this->api(sprintf($endPoint, $productId));

    $product['image'] = sprintf('%s/ecommerce/product/%s/image?max_w=%s&max_h=%s&quality=%s', $this->apiBaseUrl, $product['id'], $params['max_w'], $params['max_h'], $params['quality']
    );

    $product['name'] = array(
      'es' => strip_tags($product['name_es']),
      'ca' => strip_tags($product['name_ca']),
      'en' => strip_tags($product['name_en']),
      'fr' => strip_tags($product['name_fr']),
      'de' => strip_tags($product['name_de']),
      'nl' => strip_tags($product['name_nl']),
      'it' => strip_tags($product['name_it']),
      'ru' => strip_tags($product['name_ru'])
    );

    $product['description'] = array(
      'es' => strip_tags($product['description_es']),
      'ca' => strip_tags($product['description_ca']),
      'en' => strip_tags($product['description_en']),
      'fr' => strip_tags($product['description_fr']),
      'de' => strip_tags($product['description_de']),
      'nl' => strip_tags($product['description_nl']),
      'it' => strip_tags($product['description_it']),
      'ru' => strip_tags($product['description_ru'])
    );

    $product['category_name'] = array(
      'es' => strip_tags($product['category_name_es']),
      'ca' => strip_tags($product['category_name_ca']),
      'en' => strip_tags($product['category_name_en']),
      'fr' => strip_tags($product['category_name_fr']),
      'de' => strip_tags($product['category_name_de']),
      'nl' => strip_tags($product['category_name_nl']),
      'it' => strip_tags($product['category_name_it']),
      'ru' => strip_tags($product['category_name_ru'])
    );

    return $product;
  }

  /**
   * Gets ecommerce product calendar days available
   *
   * @param  integer  $productId ecommerce product id
   * @return array
   */
  public function getEcommerceProductDays($productId) {
    $endPoint = $this->getEndPoint('ecommerce_product_days');

    $a_days = $this->api(sprintf($endPoint, $productId));

    return $a_days;
  }

  /**
   * Gets ecommerce sale price
   *
   * @param  integer  $options['ecommerceproduct_id'] Ecommerce product ID
   * @param  integer  $options['ecommerceattributeterm_id'] Ecommerce attribute term ID
   * @param  string  $options['date'] date
   * @param  string  $options['date_in'] date in
   * @param  string  $options['date_out'] date out
   * @param  integer  $options['quantity'] quantity
   * @return array
   */
  public function getEcommerceSalePrice(array $options = array()) {
    $endPoint = $this->getEndPoint('ecommerce_sale_price');

    $params = array();
    $params['ecommerceproduct_id'] = 0;
    if (isset($options['ecommerceproduct_id'])) {
      $params['ecommerceproduct_id'] = $options['ecommerceproduct_id'];
    }
    $params['ecommerceattributeterm_id'] = 0;
    if (isset($options['ecommerceattributeterm_id'])) {
      $params['ecommerceattributeterm_id'] = $options['ecommerceattributeterm_id'];
    }
    $params['date'] = '';
    if (isset($options['date'])) {
      $params['date'] = $options['date'];
    }
    $params['date_in'] = '';
    if (isset($options['date_in'])) {
      $params['date_in'] = $options['date_in'];
    }
    $params['date_out'] = '';
    if (isset($options['date_out'])) {
      $params['date_out'] = $options['date_out'];
    }
    $params['quantity'] = 0;
    if (isset($options['quantity'])) {
      $params['quantity'] = $options['quantity'];
    }

    $sale_price = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));

    return $sale_price;
  }

  /**
   * Inserts an ecommerce sale. To get fields, consult online documentation at
   * https://hub.net2rent.com/doc/employee.php?action=show_form&filteru=&apiurl=hub.net2rent.com&usr=&section=ecommerce&call=POST+%2Fecommerce%2Fsale%2F
   */
  public function insertEcommerceSale(array $ecommerceSaleOptions) {
    $endPoint = $this->getEndPoint('ecommerce_sale');
    return $this->api($endPoint, 'POST', $ecommerceSaleOptions);
  }

  public function getBookingEcommercesales($bookingId) {
    $endPoint = $this->getEndPoint('booking_ecommercesales');
    return $this->api(sprintf($endPoint, $bookingId), 'GET');
  }

  public function getEcommerceSale($ecommerceSaleId) {
    $endPoint = $this->getEndPoint('ecommerce_sale');
    return $this->api(sprintf($endPoint . '%s', $ecommerceSaleId), 'GET');
  }

  public function insertEcommerceSalePayment($ecommerceSaleId, array $ecommerceSalePaymentOptions) {
    $endPoint = $this->getEndPoint('ecommerce_sale_payments');
    return $this->api(sprintf($endPoint, $ecommerceSaleId), 'POST', $ecommerceSalePaymentOptions);
  }

  /**
   * Gets promotional code. If exception no code available
   *
   * @param  string  $options['code'] Code
   * @param  integer  $options['typology_id'] Typology ID
   * @param  integer  $options['contact_id'] Contact ID
   * @param  string  $options['date_in'] date in
   * @return array
   */
  public function getPromotionalCode($code, array $options = array()) {
    $endPoint = $this->getEndPoint('promotional_code');

    $params = array();
    $params['code'] = '';
    if (isset($options['code'])) {
      $params['code'] = $options['code'];
    }
    $params['typology_id'] = 0;
    if (isset($options['typology_id'])) {
      $params['typology_id'] = $options['typology_id'];
    }
    $params['contact_id'] = 0;
    if (isset($options['contact_id'])) {
      $params['contact_id'] = $options['contact_id'];
    }
    $params['date_in'] = '';
    if (isset($options['date_in'])) {
      $params['date_in'] = $options['date_in'];
    }

    $promotionalcode = $this->api(sprintf($endPoint . '?%s', $code, http_build_query($params)));

    return $promotionalcode;
  }

  /**
   * Sends a mail through net2rent. To get fields, consult online documentation at
   * https://hub.net2rent.com/doc/employee.php?action=show_form&filteru=&apiurl=hub.net2rent.com&usr=admin%40company.com&pas=admin_company&section=companies&call=POST+%2Fcompanies%2F%3Acompany_id%2Femails%2F
   */
  public function sendEmail(array $emailOptions) {
    $endPoint = $this->getEndPoint('send_email');
    return $this->api($endPoint, 'POST', $emailOptions);
  }

}
