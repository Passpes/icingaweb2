<?php

/*
CREATE INDEX tgelf_comments ON icinga_comments (object_id, comment_type, comment_time);

CREATE INDEX tgelf_scheduleddowntime ON icinga_scheduleddowntime (object_id, is_in_effect, scheduled_start_time);

*/

namespace Icinga\Module\Monitoring\Object;

use Icinga\Module\Monitoring\DataView\Contact;
use Icinga\Module\Monitoring\DataView\Contactgroup;
use Icinga\Module\Monitoring\DataView\Downtime;
use Icinga\Module\Monitoring\DataView\EventHistory;
use Icinga\Module\Monitoring\DataView\Hostgroup;
use Icinga\Module\Monitoring\DataView\Comment;
use Icinga\Module\Monitoring\DataView\Servicegroup;
use Icinga\Module\Monitoring\DataView\Customvar;
use Icinga\Web\Request;

abstract class AbstractObject
{
    public $type;
    public $prefix;

    public $comments       = array();
    public $downtimes      = array();
    public $hostgroups     = array();
    public $servicegroups  = array();
    public $contacts       = array();
    public $contactgroups  = array();
    public $customvars     = array();
    public $events         = array();

    protected $view;
    private $properties = array();
    private $request    = null;

    // TODO: Fetching parent states if any would be nice
    //       Same goes for host/service dependencies

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->properties = $this->getProperties();
    }

    abstract protected function getProperties();

    public function fetchComments()
    {
        // WTF???
        $query = Comment::fromParams(array('backend' => null), array(
            'id'        => 'comment_internal_id',
            'timestamp' => 'comment_timestamp',
            'author'    => 'comment_author',
            'comment'   => 'comment_data',
            'type'      => 'comment_type',
        ))->getQuery();
        $query->where('comment_type', array('comment', 'ack'));
        $query->where('comment_objecttype', $this->type);
        $query->where('comment_host', $this->host_name);
        if ($this->type === 'service') {
            $query->where('comment_service', $this->service_description);
        }
        $this->comments = $query->fetchAll();
        return $this;
    }

    public function fetchDowntimes()
    {
        // TODO: We want to check for objecttype = 'host', not type_id = 1

        // WTF???
        $query = Downtime::fromParams(array('backend' => null), array(
            'id'           => 'downtime_internal_id',
            'objecttype'   => 'downtime_objecttype',
            'comment'      => 'downtime_comment',
            'author'       => 'downtime_author',
            'start'        => 'downtime_start',
            'scheduled_start' => 'downtime_scheduled_start',
            'end'          => 'downtime_end',
            'duration'     => 'downtime_duration',
            'is_flexible'  => 'downtime_is_flexible',
            'is_fixed'     => 'downtime_is_fixed',
            'is_in_effect' => 'downtime_is_in_effect',
            'entry_time'   => 'downtime_entry_time',
            'host'         => 'downtime_host',
            'service'      => 'downtime_service'
        ))->getQuery();

        $query->where('downtime_objecttype', $this->type);
        $query->where('downtime_host', $this->host_name);
        if ($this->type === 'service') {
            $query->where('downtime_service', $this->service_description);
        }
        $query->order('downtime_is_in_effect', 'DESC')->order('downtime_scheduled_start', 'ASC');

        $this->downtimes = $query->fetchAll();
        return $this;

        $this->downtimes = Downtime::fromRequest($this->request)->getQuery()->fetchAll();
        return $this;
    }

    public function fetchHostgroups()
    {
        $query = Hostgroup::fromRequest(
            $this->request,
            array(
                'hostgroup_name',
                'hostgroup_alias'
            )
        )->getQuery();

        $this->hostgroups = $query->fetchPairs();
        return $this;
    }

    public function fetchCustomvars()
    {
        $query = Customvar::fromRequest(
            $this->request,
            array(
                'varname',
                'varvalue'
            )
        )->getQuery();

        if ($this->type === 'host') {
            $query->where('host_name', $this->host_name)
                ->where('object_type', 'host');
        } else {
            $query->where('host_name', $this->host_name)
                ->where('object_type', 'service')
                ->where('service_description', $this->service_description);
        }

        $this->customvars = $query->fetchPairs();
        return $this;
    }

    public function fetchContacts()
    {
/*
        $query = Contact::fromRequest(
            $this->request,
            array(
                'contact_name',
                'contact_alias',
                'contact_email',
                'contact_pager',
            )
        )->getQuery()
            ->where('host_name', $this->host_name);
*/

        $query = Contact::fromParams(array('backend' => null), array(
                'contact_name',
                'contact_alias',
                'contact_email',
                'contact_pager',
        ))->getQuery();

        if ($this->type === 'service') {
            $query->where('service_host_name', $this->host_name);
            $query->where('service_description', $this->service_description);
        } else {
            $query->where('host_name', $this->host_name);
        }

        $this->contacts = $query->fetchAll();
        return $this;
    }

    public function fetchServicegroups()
    {
        $query = Servicegroup::fromRequest(
            $this->request,
            array(
                'servicegroup_name',
                'servicegroup_alias',
            )
        )->getQuery();

        $this->servicegroups = $query->fetchPairs();
        return $this;
    }

    public function fetchContactgroups()
    {

        $query = Contactgroup::fromParams(array('backend' => null), array(
                'contactgroup_name',
                'contactgroup_alias'
        ))->getQuery();

        if ($this->type === 'service') {
            $query->where('service_host_name', $this->host_name);
            $query->where('service_description', $this->service_description);
        } else {
            $query->where('host_name', $this->host_name);
        }
/*
        $query = Contactgroup::fromRequest(
            $this->request,
            array(
                'contactgroup_name',
                'contactgroup_alias'
            )
        )->getQuery();
*/
        $this->contactgroups = $query->fetchAll();

        return $this;
    }

    public function fetchEventHistory()
    {
        $query = EventHistory::fromRequest(
            $this->request,
            array(
                'object_type',
                'host_name',
                'service_description',
                'timestamp',
                'state',
                'attempt',
                'max_attempts',
                'output',
                'type'
            )
        )->sort('raw_timestamp', 'DESC')->getQuery();

        $this->eventhistory = $query;
        return $this;
    }

    public function __get($param)
    {

        if (isset($this->properties->$param)) {
            return $this->properties->$param;
        } elseif (isset($this->$param)) {
            return $this->$param;
        }
        if (substr($param, 0, strlen($this->prefix)) === $this->prefix) {
            return false;
        }
        $expandedName = $this->prefix . strtolower($param);
        return $this->$expandedName;
    }

    public static function fromRequest(Request $request)
    {
        if ($request->has('service') && $request->has('host')) {
            return new Service($request);
        } elseif ($request->has('host')) {
            return new Host($request);
        }
    }

    abstract public function populate();
}
