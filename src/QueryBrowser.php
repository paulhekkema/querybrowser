<?php

/**
 * QueryBrowser
 *
 * @link      https://gitlab.kapma.nl/paulhekkema/QueryBrowser
 * @license   MIT (see LICENSE for details)
 * @author    Paul Hekkema <paul@hekkema.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace Hekkema\QueryBrowser;

use Hekkema\QueryBrowser\Exception\InvalidArgumentException;
use Hekkema\QueryBrowser\Driver\Query\QueryDriverInterface;
use Hekkema\QueryBrowser\Driver\Request\RequestDriverInterface;
use Hekkema\QueryBrowser\Driver\Storage\StorageDriverInterface;

/**
 * QueryBrowser
 */
class QueryBrowser implements \Serializable
{
    /**
     * Prefix for request variables.
     */
    const QB_PREFIX = 'qb';

    /**
     * Unique ID
     *
     * @var string
     */
    protected $id;

    /**
     * Configuration
     *
     * @var ConfigManager
     */
    protected $config;

    /**
     * QueryDriver
     *
     * @var QueryDriverInterface
     */
    protected $queryDriver;

    /**
     * RequestDriver
     *
     * @var RequestDriverInterface
     */
    protected $requestDriver;

    /**
     * StorageDriver
     *
     * @var StorageDriverInterface
     */
    protected $storageDriver;

    /**
     * Pagenumber
     *
     * @var int
     */
    protected $page = 1;

    /**
     * Pagesize
     *
     * Set to zero for no limit
     *
     * @var int
     */
    protected $pageSize;

    /**
     * @TODO
     *
     * @var OrderBy
     */
    protected $orderBy;

    /**
     * @TODO
     *
     * @var SearchManager
     */
    protected $searchManager;

    /**
     * Constructor
     *
     * @param string $id
     * @param QueryDriverInterface $queryDriver
     *
     * @return void
     */
    public function __construct(
        QueryDriverInterface $queryDriver,
        RequestDriverInterface $requestDriver,
        StorageDriverInterface $storageDriver,
        ConfigManager $config
    ) {
        // if no id is supplied, use the one from the driver
        try {
            $id = $config->get('qb.id');
        } catch (InvalidArgumentException $e) {
            $id = $queryDriver->generateId();
        }

        $this->setId($id);
        $this->setQueryDriver($queryDriver);
        $this->setRequestDriver($requestDriver);
        $this->setStorageDriver($storageDriver);
        $this->config = $config;
        $this->orderBy = new OrderBy();
        $this->searchManager = new SearchManager();
        $this->setPageSize($this->config->get('qb.pageSize'));
    }

    /**
     * Get the id
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the id
     *
     * @param string $id
     *
     * @return self
     *
     * @throws InvalidArgumentException When id is empty or invalid
     */
    public function setId(string $id)
    {
        if ('' === $id) {
            throw new InvalidArgumentException('Identifier can not be empty.');
        }

        if (0 === preg_match('/[a-zA-Z0-9]+/', $id)) {
            throw new InvalidArgumentException(
                sprintf('Identifier can only contain alphanumeric characters (%s).', $id)
            );
        }

        // always prefix the id
        $this->id = self::QB_PREFIX.$id;

        return $this;
    }

    /**
     * Get the config
     *
     * @return ConfigManager
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Get the query driver
     *
     * @return QueryDriverInterface
     */
    public function getQueryDriver()
    {
        return $this->queryDriver;
    }

    /**
     * Set the query driver
     *
     * @param QueryDriverInterface $queryDriver
     *
     * @return self
     */
    public function setQueryDriver(QueryDriverInterface $queryDriver)
    {
        $this->queryDriver = $queryDriver;

        return $this;
    }

    /**
     * Get the request driver
     *
     * @return RequestDriverInterface
     */
    public function getRequestDriver()
    {
        return $this->requestDriver;
    }

    /**
     * Set the request driver
     *
     * @param RequestDriverInterface $requestDriver
     *
     * @return self
     */
    public function setRequestDriver(RequestDriverInterface $requestDriver)
    {
        $this->requestDriver = $requestDriver;

        return $this;
    }

    /**
     * Get the storage driver
     *
     * @return StorageDriverInterface
     */
    public function getStorageDriver()
    {
        return $this->storageDriver;
    }

    /**
     * Set the storage driver
     *
     * @param StorageDriverInterface $storageDriver
     *
     * @return self
     */
    public function setStorageDriver(StorageDriverInterface $storageDriver)
    {
        $this->storageDriver = $storageDriver;

        return $this;
    }

    /**
     * Get page
     *
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * Set page
     *
     * @param int $page
     *
     * @return self
     *
     * @throws InvalidArgumentException When $page is lower than 1
     */
    public function setPage(int $page)
    {
        if ($page < 1) {
            throw new InvalidArgumentException('$page must be 0 or higher.');
        }

        $this->page = $page;

        return $this;
    }

    /**
     * Get pagesize
     *
     * @return int
     */
    public function getPageSize()
    {
        return $this->pageSize;
    }

    /**
     * Set pagesize
     *
     * Set pagesize to zero to show all the results.
     *
     * @param int $pageSize
     *
     * @return self
     *
     * @throws InvalidArgumentException When $pageSize is lower than 0
     */
    public function setPageSize(int $pageSize)
    {
        if ($pageSize < 0) {
            throw new InvalidArgumentException('$pageSize must be 0 or higher.');
        }

        $this->pageSize = $pageSize;

        return $this;
    }

    /**
     * Get order by
     *
     * @return OrderBy
     */
    public function getOrderBy()
    {
        return $this->orderBy;
    }

    /**
     * Get search manager
     *
     * @return SearchManager
     */
    public function getSearchManager()
    {
        return $this->searchManager;
    }

    /**
     * Get the results from the driver and add this to a new Result.
     *
     * @return Result
     */
    public function execute()
    {
        // load state from storage
        $this->loadStateFromStorage();

        // get state from request
        $this->getStateFromRequest();

        // if no order by was manually set then try to get it
        // from the source/querydriver
        if ($this->orderBy->isEmpty()) {
            $this->orderBy = $this->queryDriver->getOrderBy();
        }

        // get the results from the driver
        $results = $this->queryDriver->getResults(
            $this->orderBy,
            $this->searchManager,
            $this->getOffset(),
            $this->pageSize
        );

        // get total number if results from the driver
        $totalResults = $this->queryDriver->getTotalResults($this->orderBy, $this->searchManager);

        // somehow we have a page that is out of reach
        if (count($results) === 0 && $totalResults > 0 && $this->page > 1) {
            $this->requestDriver->set('qb[id]', $this->id);
            $this->requestDriver->set('qb[page]', 1);

            return $this->execute();
        }

        // save state to storage
        $this->saveStateToStorage();

        $result = new Result($results, $totalResults, $this);

        return $result;
    }

    /**
     * Get offset
     *
     * @return int
     */
    public function getOffset()
    {
        return ($this->page - 1) * $this->pageSize;
    }

    /**
     * Serialize
     *
     * @return array
     */
    public function serialize()
    {
        return serialize([
            'id'       => $this->id,
            'page'     => $this->page,
            'pageSize' => $this->pageSize,
            'orderBy'  => $this->orderBy->toArray(),
            'search'   => $this->searchManager->toArray(),
        ]);
    }

    /**
     * Unserialize
     *
     * @param  string $data
     *
     * @return void
     */
    public function unserialize($data)
    {
        $data = unserialize($data);

        $this->loadStateFromArray($data);
    }

    /**
     * Load the state from an array.
     *
     * @param array $data
     *
     * @return void
     */
    protected function loadStateFromArray(array $data)
    {
        if (isset($data['id']) && $data['id'] === $this->id) {
            if (isset($data['page'])) {
                $this->setPage((int) $data['page']);
            }

            if (isset($data['pageSize'])) {
                $this->setPageSize((int) $data['pageSize']);
            }

            if (isset($data['search'])) {
                if (isset($data['search']['global']) && '' !== $data['search']['global']) {
                    $this->searchManager->addSearch(
                        $data['search']['global'],
                        $this->config->get('qb.search.operator'),
                        $this->config->get('qb.search.caseSensitivity')
                    );
                }
            }

            if (isset($data['orderBy'])) {
                $this->orderBy = new OrderBy(
                    $data['orderBy']['field'],
                    $data['orderBy']['direction']
                );
            }
        }
    }

    /**
     * Load state from the storage driver.
     *
     * @return void
     */
    protected function loadStateFromStorage()
    {
        $data = $this->storageDriver->get($this->id);

        if (null !== $data) {
            unserialize($data);
        }
    }

    /**
     * Get state from the request driver.
     *
     * @return void
     */
    protected function getStateFromRequest()
    {
        $data = $this->requestDriver->getAll($this->id);

        if (null !== $data && isset($data[self::QB_PREFIX])) {
            $this->loadStateFromArray($data[self::QB_PREFIX]);
        }
    }

    /**
     * Save state using the storage driver.
     *
     * @return void
     */
    protected function saveStateToStorage()
    {
        return $this->storageDriver->set($this->id, serialize($this));
    }
}
