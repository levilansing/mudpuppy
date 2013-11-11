<?php
defined('MUDPUPPY') or die('Restricted');

/**
 * Abstract class to use as the base for any module that directly represents a DataObject. This class provides default
 * implementations of the core module methods (get, getCollection, create, update, delete) that should be sufficient for
 * most DataObject types. In support of this, the concrete class must implement three methods (getStructureDefinition,
 * isValid, and sanitize), which provide the structure definition array, validate input objects (prior to creating and
 * updating), and sanitize objects (prior to returning to the user). Many objects may not need to perform any special
 * validation or sanitization, but no default implementation is provided in order to force the implementer to consider
 * such situations. There are also four other methods (prepForCreate, objectCreated, prepForUpdate, and objectUpdated)
 * that may be overridden as needed to modify fields or perform additional tasks before and after creation and update.
 * In addition to the core methods, the DataObjectModule implements an action called 'schema' that
 * returns the data object's structure definition (or schema).
 */
abstract class DataObjectModule extends Module {

    private $dataObjectName;

    /**
     * @param string $dataObjectName name of the data object class (which must inherit from DataObject)
     */
    public function __construct($dataObjectName) {
        $this->dataObjectName = $dataObjectName;
    }

    /**
     * Gets the data object for the given id.
     * @param int $id the object id
     * @return array that represents the object (sanitized)
     * @throws InvalidIdentifierException if no object is found for the given id
     * @throws ApiException if retrieved object is not a DataObject
     */
    public function get($id) {
        /** @var $dataObject DataObject */
        $dataObject = call_user_func(array($this->dataObjectName, 'get'), $id);
        if ($dataObject === null) {
            throw new InvalidIdentifierException("No object found for id: $id");
        }
        if (!is_subclass_of($dataObject, 'DataObject')) {
            throw new ApiException('Failed to retrieve object');
        }
        return $this->sanitize($dataObject->toArray());
    }

    /**
     * Gets a collection of data objects. The default implementation returns ALL objects. Override the
     * retrieveDataObjects method to support filtering based on the given query parameters.
     * @param array $params array of query parameters that came in with the request
     * @return array of arrays that represent the data objects (sanitized)
     * @throws ApiException if retrieveDataObjects doesn't return an array of DataObjects
     */
    public function getCollection($params) {
        $dataObjects = $this->retrieveDataObjects($params);
        if ($dataObjects === null || !is_array($dataObjects)) {
            throw new ApiException('Failed to retrieve objects');
        }
        $output = array();
        foreach ($dataObjects as $dataObject) {
            /** @var $dataObject DataObject */
            if (!($dataObject instanceof DataObject)) {
                throw new ApiException('Failed to retrieve objects');
            }
            $output[] = $this->sanitize($dataObject->toArray());
        }
        return $output;
    }

    /**
     * Creates a data object from the given array representation.
     * @param array $object array representation of the object
     * @return array that represents the newly created object
     * @throws InvalidInputException if the input object does not validate
     * @throws InvalidIdentifierException if the object already exists
     * @throws ApiException if the object fails to create
     */
    public function create($object) {
        $object = Module::cleanArray($object, $this->getStructureDefinition());
        if (!$this->isValid($object)) {
            throw new InvalidInputException('The specified object is invalid');
        }
        /** @var $dataObject DataObject */
        $dataObject = new $this->dataObjectName();
        foreach ($object as $field => $value) {
            $dataObject->$field = $value;
        }
        if ($dataObject->exists()) {
            throw new InvalidIdentifierException('The specified object already exists');
        }
        $this->prepForCreate($dataObject);
        if (!$dataObject->save()) {
            throw new ApiException('Failed to create object');
        }
        $this->objectCreated($dataObject);
        return $this->sanitize($dataObject->toArray());
    }

    /**
     * Updates a data object based on the given array representation.
     * @param int $id the object id
     * @param array $object array representation of the object
     * @return array that represents the updated object
     * @throws InvalidInputException if the input object does not validate
     * @throws InvalidIdentifierException if the object doesn't exists
     * @throws ApiException if the object fails to update
     */
    public function update($id, $object) {
        $object = Module::cleanArray($object, $this->getStructureDefinition());
        if (!$this->isValid($object)) {
            throw new InvalidInputException('The specified object is invalid');
        }
        /** @var $dataObject DataObject */
        $dataObject = call_user_func(array($this->dataObjectName, 'get'), $id);
        if ($dataObject === null) {
            throw new InvalidIdentifierException('The specified object does not exist');
        }
        foreach ($object as $field => $value) {
            $dataObject->$field = $value;
        }
        $this->prepForUpdate($dataObject);
        if (!$dataObject->save()) {
            throw new ApiException('Failed to update object');
        }
        $this->objectUpdated($dataObject);
        return $this->sanitize($dataObject->toArray());
    }

    /**
     * Deletes a data object for the given id.
     * @param int $id the object id
     * @throws InvalidIdentifierException if no object is found for the given id
     * @throws ApiException if the object fails to delete
     */
    public function delete($id) {
        /** @var $dataObject DataObject */
        $dataObject = new $this->dataObjectName($id);
        if (!$dataObject->exists()) {
            throw new InvalidIdentifierException("No object found for id: $id");
        }
        if (!$dataObject->delete()) {
            throw new ApiException("Failed to delete object with id: $id");
        }
    }

    /**
     * Action handler to get the data object structure definition (or schema).
     * @param $params
     * @return array
     */
    public function action_schema($params) {
        return $this->getStructureDefinition();
    }

    /**
     * Retrieves an array of DataObjects for use by getCollection. The default implementation returns ALL objects.
     * Override to support filtering based on the query parameters.
     * @param array $params array of query parameters that came in with the request
     * @return array(DataObject)
     */
    protected function retrieveDataObjects($params) {
        return call_user_func(array($this->dataObjectName, 'getAll'));
    }

    /**
     * Gets the structure definition array for the data object. See Module::cleanArray() for details.
     * Defaults to the defined data object structure.
     * Override to customize.
     * @return array
     */
    protected function getStructureDefinition() {
        return call_user_func(array($this->dataObjectName, 'getStructureDefinition'));
    }

    /**
     * Determines whether an input object is valid prior to creating or updating it. Note: the input array has already
     * been cleaned and validated against the structure definition.
     * @param $object array representation of the object
     * @return boolean true if valid
     */
    protected abstract function isValid($object);

    /**
     * Sanitizes the array representation of the object prior to returning to the user.
     * @param array $object array representation of the object
     * @return array that represents the sanitized object
     */
    protected abstract function sanitize($object);

    /**
     * Prepares the given data object for creation. This gives the concrete class a chance to modify fields or perform
     * other related tasks before the object is actually created in the database.
     * @param DataObject $dataObject the object to be prepared
     */
    protected function prepForCreate($dataObject) {
        // Nothing
    }

    /**
     * Notification that the data object has been created in the database. This gives the concrete class a chance to
     * perform any additional tasks that may be required to finish creating the object.
     * @param DataObject $dataObject the object that was created
     */
    protected function objectCreated($dataObject) {
        // Nothing
    }

    /**
     * Prepares the given data object for update. This gives the concrete class a chance to modify fields or perform
     * other related tasks before the object is actually updated in the database.
     * @param DataObject $user the object to be prepared
     */
    protected function prepForUpdate($user) {
        // Nothing
    }

    /**
     * Notification that the data object has been updated in the database. This gives the concrete class a chance to
     * perform any additional tasks that may be required to finish updating the object.
     * @param DataObject $dataObject the object that was updated
     */
    protected function objectUpdated($dataObject) {
        // Nothing
    }

}

?>