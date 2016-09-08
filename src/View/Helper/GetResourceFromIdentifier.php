<?php

namespace CleanUrl\View\Helper;

/**
 * Clean Url Get Resource From Identifier
 *
 * @copyright Copyright 2007-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

use Doctrine\DBAL\Connection;
use Zend\Authentication\AuthenticationService;
use Zend\View\Helper\AbstractHelper;
use Omeka\Api\Adapter\Manager as ApiAdapterManager;

/**
 * @package Omeka\Plugins\CleanUrl\View\Helper
 */
class GetResourceFromIdentifier extends AbstractHelper
{
    protected $connection;
    protected $apiAdapterManager;
    protected $authenticationService;

    public function __construct(Connection $connection, ApiAdapterManager $apiAdapterManager, AuthenticationService $authenticationService)
    {
        $this->connection = $connection;
        $this->apiAdapterManager = $apiAdapterManager;
        $this->authenticationService = $authenticationService;
    }

    /**
     * Get resource from identifier
     *
     * @param string $identifier The identifier of the resource to find.
     * @param boolean $withPrefix Optional. If identifier begins with prefix.
     * @param string $resourceName Optional. Search a specific resource type if any.
     * @return Omeka\Api\Representation\AbstractResourceRepresentation The resource.
     */
    public function __invoke($identifier, $withPrefix = false, $resourceName = null)
    {
        $identifier = rawurldecode($identifier);
        if (empty($identifier)) {
            return null;
        }

        $bind = [];

        $propertyId = (integer) $this->view->setting('clean_url_identifier_property');

        if ($resourceName) {
            $apiAdapter = $this->apiAdapterManager->get($resourceName);
            $resourceType = $apiAdapter->getEntityClass();

            $sqlResourceType = "AND resource.resource_type = ?";
            $bind[] = $resourceType;
            $sqlOrder = 'ORDER BY value.resource_id, value.id';
        }
        else {
            $sqlResourceType = '';
            $sqlOrder = "ORDER BY FIELD(resource.resource_type, 'Omeka\Entity\ItemSet', 'Omeka\Entity\Item', 'Omeka\Entity\Media'), value.resource_id, value.id";
        }

        $identity = $this->authenticationService->getIdentity();
        $sqlWhereIsPublic = '';
        if (!$identity) {
            $sqlWhereIsPublic = 'AND resource.is_public = 1';
        }

        if ($withPrefix) {
            // If the table is case sensitive, lower-case the search.
            if ($this->view->setting('clean_url_case_insensitive')) {
                $bind[] = strtolower($identifier);
                $sqlWhereText = 'AND LOWER(value.value) = ?';
            }
            // Default.
            else {
                $bind[] = $identifier;
                $sqlWhereText = 'AND value.value = ?';
            }
        }
        else {
            $prefix = $this->view->setting('clean_url_identifier_prefix');
            $identifiers = [
                $prefix . $identifier,
                $prefix . ' ' . $identifier, // Check with a space between prefix and identifier too.
            ];
            // Check prefix with a space and a no-break space.
            if ($this->view->setting('clean_url_identifier_unspace')) {
                $unspace = str_replace(array(' ', ' '), '', $prefix);
                if ($prefix != $unspace) {
                    // Check with a space between prefix and identifier too.
                    $identifiers[] = $unspace . $identifier;
                    $identifiers[] = $unspace . ' ' . $identifier;
                }
            }
            $in = implode(',', array_fill(0, count($identifiers), '?'));

            // If the table is case sensitive, lower-case the search.
            if ($this->view->setting('clean_url_case_insensitive')) {
                $identifiers = array_map('strtolower', $identifiers);
                $sqlWhereText = "AND LOWER(value.value) IN ($in)";
            }
            // Default.
            else {
                $sqlWhereText = "AND value.value IN ($in)";
            }
            $bind = array_merge($bind, $identifiers);
        }

        $sql = "
            SELECT resource.resource_type, value.resource_id
            FROM value
                LEFT JOIN resource ON (value.resource_id = resource.id)
            WHERE value.property_id = '$propertyId'
                $sqlResourceType
                $sqlWhereText
                $sqlWhereIsPublic
            $sqlOrder
            LIMIT 1
        ";
        $result = $this->connection->fetchAssoc($sql, $bind);

        $resource = null;
        if ($result) {
            $resource = $this->view->api()->read($resourceName, $result['resource_id'])->getContent();
        } elseif ($resourceName) {
            // Return the resource via the Omeka id.
            $id = (integer) $identifier;
            if ($id !== 0) {
                $resource = $this->view->api()->read($resourceName, $id)->getContent();
            }
        }

        return $resource;
    }
}
