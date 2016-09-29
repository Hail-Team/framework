<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Hail\Cache\Driver;

use Hail\Cache\Driver;
use SQLite3 as SQLite;
use SQLite3Result;

/**
 * SQLite3 cache provider.
 *
 * @since  1.4
 * @author Jake Bell <jake@theunraveler.com>
 * @author FlyingHail <flyinghail@msn.com>
 */
class SQLite3 extends Driver
{
    /**
     * The ID field will store the cache key.
     */
    const ID_FIELD = 'k';

    /**
     * The data field will store the serialized PHP value.
     */
    const DATA_FIELD = 'd';

    /**
     * The expiration field will store a date value indicating when the
     * cache entry should expire.
     */
    const EXPIRATION_FIELD = 'e';

    /**
     * @var SQLite3
     */
    private $sqlite;

    /**
     * @var string
     */
    private $table;

    /**
     * Constructor.
     *
     * Calling the constructor will ensure that the database file and table 
     * exist and will create both if they don't.
     *
     * @param SQLite $sqlite
     * @param string $table
     */
    public function __construct(SQLite $sqlite, $table)
    {
        $this->sqlite = $sqlite;
        $this->table  = (string) $table;

        list($id, $data, $exp) = $this->getFields();

        $this->sqlite->exec("CREATE TABLE IF NOT EXISTS $table ($id TEXT PRIMARY KEY NOT NULL, $data BLOB, $exp INTEGER)");
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch($id)
    {
        if ($item = $this->findById($id)) {
            return unserialize($item[self::DATA_FIELD]);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function doContains($id)
    {
        return null !== $this->findById($id, false);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave($id, $data, $lifetime = 0)
    {
	    $fields = implode(',', $this->getFields());
        $statement = $this->sqlite->prepare("INSERT OR REPLACE INTO {$this->table} ($fields) VALUES (:id, :data, :expire)");

        $statement->bindValue(':id', $id);
        $statement->bindValue(':data', serialize($data), SQLITE3_BLOB);
        $statement->bindValue(':expire', $lifetime > 0 ? time() + $lifetime : null);

        return $statement->execute() instanceof SQLite3Result;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete($id)
    {
        list($idField) = $this->getFields();

        $statement = $this->sqlite->prepare("DELETE FROM {$this->table} WHERE $idField = :id");

        $statement->bindValue(':id', $id);

        return $statement->execute() instanceof SQLite3Result;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFlush()
    {
        return $this->sqlite->exec("DELETE FROM $this->table");
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetStats()
    {
        // no-op.
    }

    /**
     * Find a single row by ID.
     *
     * @param mixed $id
     * @param bool $includeData
     *
     * @return array|null
     */
    private function findById($id, $includeData = true)
    {
        list($idField) = $fields = $this->getFields();

        if (!$includeData) {
            $key = array_search(static::DATA_FIELD, $fields);
            unset($fields[$key]);
        }

	    $fields = implode(',', $fields);
        $statement = $this->sqlite->prepare(
	        "SELECT $fields FROM {$this->table} WHERE $idField = :id LIMIT 1"
        );

        $statement->bindValue(':id', $id, SQLITE3_TEXT);

        $item = $statement->execute()->fetchArray(SQLITE3_ASSOC);

        if ($item === false) {
            return null;
        }

        if ($this->isExpired($item)) {
            $this->doDelete($id);

            return null;
        }

        return $item;
    }

    /**
     * Gets an array of the fields in our table.
     *
     * @return array
     */
    private function getFields()
    {
        return array(static::ID_FIELD, static::DATA_FIELD, static::EXPIRATION_FIELD);
    }

    /**
     * Check if the item is expired.
     *
     * @param array $item
     *
     * @return bool
     */
    private function isExpired(array $item)
    {
        return isset($item[static::EXPIRATION_FIELD]) &&
            $item[self::EXPIRATION_FIELD] !== null &&
            $item[self::EXPIRATION_FIELD] < NOW;
    }
}