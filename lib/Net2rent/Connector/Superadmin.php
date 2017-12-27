<?php
namespace Net2rent\Connector;

class Superadmin extends AbstractConnector
{

    protected $_endPoints = array(
        'companies' => '/companies/',
        'bookings' => '/companies/%s/bookings',
        'booking' => '/bookings/',
		'users' => '/users/'
    );
    
    /**
     * Gets companies
     *
     * @param  boolean  $options['automatic_prebooking_alert'] returns only companies with automatic_prebooking_alert check
     * @param  boolean  $options['automatic_prebooking_cancel'] returns only companies with automatic_prebooking_cancel check
     * @param  boolean  $options['automatic_tpv_booking_cancel'] returns only companies with automatic_tpv_booking_cancel check
     * @param  boolean  $options['full'] returns full company fields
     * @return array  (items)
     */
    public function getCompanies(array $options = array())
    {
        $endPoint = $this->getEndPoint('companies');
        
        $params = array();
        $params['automatic_prebooking_alert']=0;
        $params['automatic_prebooking_cancel']=0;
        $params['automatic_tpv_booking_cancel']=0;
        $params['full']=0;
        if(isset($options['automatic_prebooking_alert'])) {
            $params['automatic_prebooking_alert'] = (int) $options['automatic_prebooking_alert'];
        }
        if(isset($options['automatic_prebooking_cancel'])) {
            $params['automatic_prebooking_cancel'] = (int) $options['automatic_prebooking_cancel'];
        }
        if(isset($options['automatic_tpv_booking_cancel'])) {
            $params['automatic_tpv_booking_cancel'] = (int) $options['automatic_tpv_booking_cancel'];
        }
        if(isset($options['full'])) {
            $params['full'] = (int) $options['full'];
        }
        
        return $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
    }
    
    /**
     * Get company by id
     * @return array
     */
    public function getCompany($companyId)
    {
        $endPoint = $this->getEndPoint('companies');
        return $this->api(sprintf($endPoint . '%s',$companyId));
    }

    /**
     * Gets bookings
     *
     * @param  string   $options['status'] status of the booking valid values:[available, blocked, prebooked, booked, cancelled] default empty = all
     * @param  boolean  $options['tpv'] returns only bookings with tpv check
     * @return array  (items)
     */
    public function getBookings($companyId,array $options = array())
    {
        $endPoint = $this->getEndPoint('bookings');
        
        $params = array();
        $params['status']="";
        if(isset($options['status']) && $options['status']) {
            $params['status'] = $options['status'];
        }
        if(isset($options['tpv'])) {
            $params['tpv'] = (int) $options['tpv'];
        }
        
        return $this->api(sprintf($endPoint . '?%s',$companyId, http_build_query($params)));
    }
    
    public function getBooking($bookingId)
    {
        $endPoint = $this->getEndPoint('booking');
        return $this->api(sprintf($endPoint . '%s',$bookingId));
    }
  
    /**
     * Cancels a booking. To get fields, consult online documentation at  
     * https://hub.net2rent.com/doc/employee.php?action=show_form&filteru=&apiurl=hub.net2rent.com&usr=admin%40company.com&pas=admin_company&section=bookings&call=POST+%2Fbookings%2F
     */
    public function cancelBooking($bookingId,array $bookingOptions)
    {
        $endPoint = $this->getEndPoint('booking');
        return $this->api(sprintf($endPoint.'%s',$bookingId), 'PUT', $bookingOptions);
    }
	
	/**
     * Get user by usr
     * @return array
     */
    public function getUser($usr)
    {
        $endPoint = $this->getEndPoint('users');
        return $this->api(sprintf($endPoint . '%s',$usr));
    }
 
}