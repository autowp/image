<?php

namespace Autowp\Image\Storage\DbTable;

use Zend_Db_Table_Abstract;

class FormatedImage extends Zend_Db_Table_Abstract
{
    protected $_primary = ['image_id', 'format'];
}
