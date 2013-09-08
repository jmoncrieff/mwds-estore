<?php
/**
 * abook_local_file.php
 *
 * Copyright (c) 1999-2005 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * @version $Id: abook_local_file.php,v 1.1 2005/06/14 13:52:57 indigoleopard Exp $
 * @package squirrelmail
 * @subpackage addressbook
 */

/**
 * Backend for address book as a pipe separated file
 *
 * Stores the address book in a local file
 *
 * An array with the following elements must be passed to
 * the class constructor (elements marked ? are optional):
 *<pre>
 *   filename  => path to addressbook file
 * ? create    => if true: file is created if it does not exist.
 * ? umask     => umask set before opening file.
 * ? name      => name of address book.
 * ? detect_writeable => detect address book access permissions by
 *                checking file permissions.
 * ? writeable => allow writing into address book. Used only when
 *                detect_writeable is set to false.
 *</pre>
 * NOTE. This class should not be used directly. Use the
 *       "AddressBook" class instead.
 * @package squirrelmail
 */
class abook_local_file extends addressbook_backend {
    /**
     * Backend type
     * @var string
     */
    var $btype = 'local';
    /**
     * Backend name
     * @var string
     */
    var $bname = 'local_file';

    /**
     * File used to store data
     * @var string
     */
    var $filename = '';
    /**
     * File handle
     * @var object
     */
    var $filehandle = 0;
    /**
     * Create file, if it not present
     * @var bool
     */
    var $create = false;
    /**
     * Detect, if address book is writeable by checking file permisions
     * @var bool
     */
    var $detect_writeable   = true;
    /**
     * Control write access to address book
     *
     * Option does not have any effect, if 'detect_writeable' is 'true'
     * @var bool
     */
    var $writeable = false;
    /**
     * Umask of the file
     * @var string
     */
    var $umask;

    /* ========================== Private ======================= */

    /**
     * Constructor
     * @param array $param backend options
     * @return bool
     */
    function abook_local_file($param) {
        $this->sname = _("Personal address book");
        $this->umask = Umask();

        if(is_array($param)) {
            if(empty($param['filename'])) {
                return $this->set_error('Invalid parameters');
            }
            if(!is_string($param['filename'])) {
                return $this->set_error($param['filename'] . ': '.
                     _("Not a file name"));
            }

            $this->filename = $param['filename'];

            if(isset($param['create'])) {
                $this->create = $param['create'];
            }
            if(isset($param['umask'])) {
                $this->umask = $param['umask'];
            }
            if(isset($param['name'])) {
                $this->sname = $param['name'];
            }
            if(isset($param['detect_writeable'])) {
                $this->detect_writeable = $param['detect_writeable'];
            }
            if(!empty($param['writeable'])) {
                $this->writeable = $param['writeable'];
            }

            $this->open(true);
        } else {
            $this->set_error('Invalid argument to constructor');
        }
    }

    /**
     * Open the addressbook file and store the file pointer.
     * Use $file as the file to open, or the class' own
     * filename property. If $param is empty and file is
     * open, do nothing.
     * @param bool $new is file already opened
     * @return bool
     */
    function open($new = false) {
        $this->error = '';
        $file   = $this->filename;
        $create = $this->create;
        $fopenmode = ($this->writeable ? 'a+' : 'r');

        /* Return true is file is open and $new is unset */
        if($this->filehandle && !$new) {
            return true;
        }

        /* Check that new file exitsts */
        if((!(file_exists($file) && is_readable($file))) && !$create) {
            return $this->set_error("$file: " . _("No such file or directory"));
        }

        /* Close old file, if any */
        if($this->filehandle) { $this->close(); }

        umask($this->umask);
        if (! $this->detect_writeable) {
            $fh = @fopen($file,$fopenmode);
            if ($fh) {
                $this->filehandle = &$fh;
                $this->filename = $file;
            } else {
                return $this->set_error("$file: " . _("Open failed"));
            }
        } else {
            /* Open file. First try to open for reading and writing,
             * but fall back to read only. */
            $fh = @fopen($file, 'a+');
            if($fh) {
                $this->filehandle = &$fh;
                $this->filename   = $file;
                $this->writeable  = true;
            } else {
                $fh = @fopen($file, 'r');
                if($fh) {
                    $this->filehandle = &$fh;
                    $this->filename   = $file;
                    $this->writeable  = false;
                } else {
                    return $this->set_error("$file: " . _("Open failed"));
                }
            }
        }
        return true;
    }

    /** Close the file and forget the filehandle */
    function close() {
        @fclose($this->filehandle);
        $this->filehandle = 0;
        $this->filename   = '';
        $this->writable   = false;
    }

    /** Lock the datafile - try 20 times in 5 seconds */
    function lock() {
        for($i = 0 ; $i < 20 ; $i++) {
            if(flock($this->filehandle, 2 + 4))
                return true;
            else
                usleep(250000);
        }
        return false;
    }

    /** Unlock the datafile */
    function unlock() {
        return flock($this->filehandle, 3);
    }

    /**
     * Overwrite the file with data from $rows
     * NOTE! Previous locks are broken by this function
     * @param array $rows new data
     * @return bool
     */
    function overwrite(&$rows) {
        $this->unlock();
        $newfh = @fopen($this->filename.'.tmp', 'w');

        if(!$newfh) {
            return $this->set_error($this->filename. '.tmp:' . _("Open failed"));
        }

        for($i = 0, $cnt=sizeof($rows) ; $i < $cnt ; $i++) {
            if(is_array($rows[$i])) {
                for($j = 0, $cnt_part=count($rows[$i]) ; $j < $cnt_part ; $j++) {
                    $rows[$i][$j] = $this->quotevalue($rows[$i][$j]);
                }
                $tmpwrite = sq_fwrite($newfh, join('|', $rows[$i]) . "\n");
                if ($tmpwrite === FALSE) {
                    return $this->set_error($this->filename . '.tmp:' . _("Write failed"));
                }
            }
        }

        fclose($newfh);
        if (!@copy($this->filename . '.tmp' , $this->filename)) {
          return $this->set_error($this->filename . ':' . _("Unable to update"));
        }
        @unlink($this->filename . '.tmp');
        $this->unlock();
        $this->open(true);
        return true;
    }

    /* ========================== Public ======================== */

    /**
     * Search the file
     * @param string $expr search expression
     * @return array search results
     */
    function search($expr) {

        /* To be replaced by advanded search expression parsing */
        if(is_array($expr)) { return; }

        /* Make regexp from glob'ed expression
         * May want to quote other special characters like (, ), -, [, ], etc. */
        $expr = str_replace('?', '.', $expr);
        $expr = str_replace('*', '.*', $expr);

        $res = array();
        if(!$this->open()) {
            return false;
        }
        @rewind($this->filehandle);

        while ($row = @fgetcsv($this->filehandle, 2048, '|')) {
            $line = join(' ', $row);
            if(eregi($expr, $line)) {
                array_push($res, array('nickname'  => $row[0],
                    'name'      => $row[1] . ' ' . $row[2],
                    'firstname' => $row[1],
                    'lastname'  => $row[2],
                    'email'     => $row[3],
                    'label'     => $row[4],
                    'backend'   => $this->bnum,
                    'source'    => &$this->sname));
            }
        }

        return $res;
    }

    /**
     * Lookup alias
     * @param string $alias alias
     * @return array search results
     */
    function lookup($alias) {
        if(empty($alias)) {
            return array();
        }

        $alias = strtolower($alias);

        $this->open();
        @rewind($this->filehandle);

        while ($row = @fgetcsv($this->filehandle, 2048, '|')) {
            if(strtolower($row[0]) == $alias) {
                return array('nickname'  => $row[0],
                  'name'      => $row[1] . ' ' . $row[2],
                  'firstname' => $row[1],
                  'lastname'  => $row[2],
                  'email'     => $row[3],
                  'label'     => $row[4],
                  'backend'   => $this->bnum,
                  'source'    => &$this->sname);
            }
        }

        return array();
    }

    /**
     * List all addresses
     * @return array list of all addresses
     */
    function list_addr() {
        $res = array();
        $this->open();
        @rewind($this->filehandle);

        while ($row = @fgetcsv($this->filehandle, 2048, '|')) {
            array_push($res, array('nickname'  => $row[0],
                'name'      => $row[1] . ' ' . $row[2],
                'firstname' => $row[1],
                'lastname'  => $row[2],
                'email'     => $row[3],
                'label'     => $row[4],
                'backend'   => $this->bnum,
                'source'    => &$this->sname));
        }
        return $res;
    }

    /**
     * Add address
     * @param array $userdata new data
     * @return bool
     */
    function add($userdata) {
        if(!$this->writeable) {
            return $this->set_error(_("Addressbook is read-only"));
        }
        /* See if user exists already */
        $ret = $this->lookup($userdata['nickname']);
        if(!empty($ret)) {
            return $this->set_error(sprintf(_("User '%s' already exist"),
                   $ret['nickname']));
        }

        /* Here is the data to write */
        $data = $this->quotevalue($userdata['nickname']) . '|' .
                $this->quotevalue($userdata['firstname']) . '|' .
                $this->quotevalue($userdata['lastname']) . '|' .
                $this->quotevalue($userdata['email']) . '|' .
                $this->quotevalue($userdata['label']);

        /* Strip linefeeds */
        $data = ereg_replace("[\r\n]", ' ', $data);
        /* Add linefeed at end */
        $data = $data . "\n";

        /* Reopen file, just to be sure */
        $this->open(true);
        if(!$this->writeable) {
            return $this->set_error(_("Addressbook is read-only"));
        }

        /* Lock the file */
        if(!$this->lock()) {
            return $this->set_error(_("Could not lock datafile"));
        }

        /* Write */
        $r = sq_fwrite($this->filehandle, $data);

        /* Unlock file */
        $this->unlock();

        /* Test write result */
        if($r === FALSE) {
            /* Fail */
            $this->set_error(_("Write to addressbook failed"));
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Delete address
     * @param string $alias alias that has to be deleted
     * @return bool
     */
    function remove($alias) {
        if(!$this->writeable) {
            return $this->set_error(_("Addressbook is read-only"));
        }

        /* Lock the file to make sure we're the only process working
         * on it. */
        if(!$this->lock()) {
            return $this->set_error(_("Could not lock datafile"));
        }

        /* Read file into memory, ignoring nicknames to delete */
        @rewind($this->filehandle);
        $i = 0;
        $rows = array();
        while($row = @fgetcsv($this->filehandle, 2048, '|')) {
            if(!in_array($row[0], $alias)) {
                $rows[$i++] = $row;
            }
        }

        /* Write data back */
        if(!$this->overwrite($rows)) {
            $this->unlock();
            return false;
        }

        $this->unlock();
        return true;
    }

    /**
     * Modify address
     * @param string $alias modified alias
     * @param array $userdata new data
     * @return bool true, if operation successful
     */
    function modify($alias, $userdata) {
        if(!$this->writeable) {
            return $this->set_error(_("Addressbook is read-only"));
        }

        /* See if user exists */
        $ret = $this->lookup($alias);
        if(empty($ret)) {
            return $this->set_error(sprintf(_("User '%s' does not exist"),
                $alias));
        }

        /* Lock the file to make sure we're the only process working
         * on it. */
        if(!$this->lock()) {
            return $this->set_error(_("Could not lock datafile"));
        }

        /* Read file into memory, modifying the data for the
         * user identified by $alias */
        $this->open(true);
        @rewind($this->filehandle);
        $i = 0;
        $rows = array();
        while($row = @fgetcsv($this->filehandle, 2048, '|')) {
            if(strtolower($row[0]) != strtolower($alias)) {
                $rows[$i++] = $row;
            } else {
                $rows[$i++] = array(0 => $userdata['nickname'],
                                    1 => $userdata['firstname'],
                                    2 => $userdata['lastname'],
                                    3 => $userdata['email'],
                                    4 => $userdata['label']);
            }
        }

        /* Write data back */
        if(!$this->overwrite($rows)) {
            $this->unlock();
            return false;
        }

        $this->unlock();
        return true;
    }

    /**
     * Function for quoting values before saving
     * @param string $value string that has to be quoted
     * @param string quoted string
     */
    function quotevalue($value) {
        /* Quote the field if it contains | or ". Double quotes need to
         * be replaced with "" */
        if(ereg("[|\"]", $value)) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }

} /* End of class abook_local_file */
?>