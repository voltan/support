<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

/**
 * @author Hossein Azizabadi <azizabadi@faragostaresh.com>
 */
namespace Module\Support\Api;

use Pi;
use Pi\Application\Api\AbstractBreadcrumbs;

class Breadcrumbs extends AbstractBreadcrumbs
{
    /**
     * {@inheritDoc}
     */
    public function load()
    {
        // Get params
        $params = Pi::service('url')->getRouteMatch()->getParams();
        // Set module link
        $moduleData = Pi::registry('module')->read($this->getModule());
        // Set index link
        if ($params['controller'] == 'index') {
            $href = '';
        } else {
            $href = Pi::service('url')->assemble('default', array(
                'module' => $this->getModule(),
            ));
        }
        // Set result
        $result = array();
        $result[] = array(
            'label' => $moduleData['title'],
            'href'  => $href,
        );
        // Set module internal links
        switch ($params['controller']) {
            case 'ticket':
                if (isset($params['id']) && !empty($params['id']) && $params['id'] > 0) {
                    $ticket = Pi::api('ticket', 'support')->getTicket($params['id']);
                    $result[] = array(
                        'label' => $ticket['subject'],
                    );
                } else {
                    $result[] = array(
                        'label' => __('New support ticket'),
                    );
                }

                break;
        }

        return $result;
    }
}
