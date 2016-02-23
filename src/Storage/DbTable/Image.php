<?php

namespace Autowp\Image\Storage\DbTable;

use Zend_Db_Table_Abstract;

class Image extends Zend_Db_Table_Abstract
{
    protected $_primary = 'id';
}