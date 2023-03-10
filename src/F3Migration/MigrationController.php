<?php

namespace F3Migration;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class MigrationController extends \Prefab
{
    protected array $setting;

    protected array $stats = [];

    public \Base $f3;

    public function __construct() {
        $this->f3 =  \Base::instance();
        $this->load_setting();
        $this->initRoutes();
    }

    /**
     * Get and set the settings option.
     */
    private function load_setting() {
        $setting = [];

        $file = dirname(__DIR__) . "/data/log.log";
        $logger = new Logger('migration-'.date('Y-m-d').'.log');
        // Default setting
        $this->setting = array_merge([
            "info" => dirname(__DIR__) . "/data/migration.json",
            "path" => "database/migrations/",
            "prefix" => "Migration\\",
            "show_log" => true,
            "access_path" => "GET @ilgar: /ilgar/migrate",
            "logger" => $logger,
            "no_exception" => false
        ], $setting);
        $this->f3->set('ILGAR', $this->setting);

        if($this->setting['show_log']) {
            if(!(php_sapi_name() === 'cli')) {
                header('Content-type: text/plain');
            }

            $file = "php://output";
        }

        $logger->pushHandler(new StreamHandler($file, Logger::INFO));
    }

    /**
     * Triggers migration and much more.
     * NOT RECOMENDED FOR PUBLIC USE. PLEASE USE Boot::trigger_on().
     *
     * @return array quick stats
     */
    public function do_migrate() {
        $this->load_setting();
        $path = $this->setting['path'];
        $log = $this->setting['logger'];

        $log->notice("Migration Started");
        $migration_packages = scandir($path);


        
        $points = array_splice($migration_packages, 2);
        natcasesort($points);

        $prefix = $this->setting['prefix'];

        var_dump($prefix);

        $points = array_map(function($file) use ($path, $prefix, &$log){
            $fname = basename($file);
            $log->info('Proccessing ' . $file);
            if(!is_file(realpath($path . $file))){
                $log->info('Current file was not a file.');
                return null;
            }

            $components = [];

            preg_match("/([0-9]+)\-([\w\_]+).php/i", $fname, $components);

            if(!$components || !$components[1] || !$components[2]){
                $log->warning('The file name is in not a valid name convention. Skipping...');
                return null;
            }

            $log->info("Found " . $components[2] . " (v-" . intval($components[1]) . ")");

            return [
                "version" => intval($components[1]),
                "classname" => $prefix .$components[2],
                "path" => $path . $file
            ];
        }, $points);

        $points = array_filter($points, function($data){
            return $data!=null;
        });
        $log->notice('Found ' . count($points) . " migrations.");

        $migration_path = $this->setting['info'];

        $current = -1;
        if(file_exists($migration_path)) {
            $migrate = file_get_contents($migration_path);
            $migrate = json_decode($migrate, true);
            if(array_key_exists('version', $migrate)) {
                $current = $migrate['version'];
            }
        }

        $log->notice("Info file loaded. Current migration version: " . $current);
        //filter the version here.
        $points = array_filter($points, function($data) use($current) {
            return ($data['version'] > $current);
        });

        $log->notice('Aplicable migrations: ' . count($points));
        $cls = null;
        $counter = 0;
        $skipped = [];
        $failed = null;
        try {
            array_map(function($mig_point) use (&$current, &$cls, &$counter, &$skipped, &$log){
                include($mig_point['path']);

                $log->info("Loading " . $mig_point['classname']);
                //call the class:
                $cls = new $mig_point['classname']();

                if(!$cls->is_migratable()) {
                    $log->warning('Skipping ' . $mig_point['classname'] . ' as its marked itself not aplicable');
                    $skipped[] = $cls;
                    return;
                }

                $log->notice("Applying migration...");
                if($cls->pre_migrate() === false || $cls->on_migrate() === false || $cls->post_migrate() === false) {
                    $log->notice('The migration returns a soft error.');
                    $log->notice('Raising Exceptions.');
                    throw new \Exception("Migration failed at file " . $mig_point['classname']);
                }
                $current = $mig_point['version'];
                $counter++;
            }, $points);
        } catch (\Throwable $e) {
            $log->critical('Exception: ' . $e->getMessage() . ". On " . $e->getFile() . "#L" . $e->getLine() . ".");
            $log->notice('An exception has raised, aborting migration, and now doing soft undo...');
            try {
                $cls->on_failed($e);
                $log->notice('Soft undo successfull');
            } catch(\Throwable $e) {
                $log->critical("Undo failed. Exception raised : " . $e->getMessage() . ". On " . $e->getFile() . "#L" . $e->getLine() . ".");
            }
            $failed = $e;
        }

        $log->notice("Migration finished. Version updated to " . $current);
        //saving migration point
        file_put_contents($migration_path, json_encode([
            "version" => $current
        ]));


        // pikirin lagi si lognya
        $log->info("Successfully done " . $counter . " migration(s).");
        if($failed) {
            $log->info("and encountered exception: " . $failed->getMessage());
        }

        $this->stats = [
            "success" => $counter,
            "last_exception" => $failed,
            "version" => $current
        ];

        if($failed && !$this->setting['no_exception']) {
            throw new \RuntimeException("Migration failed with exception " . $e->getMessage() . ". On " . $e->getFile() . "#L" . $e->getLine() . ".", 0, $failed);
        }

        return $this->stats;
    }

    /**
     * Get last migration stats.
     *
     * @return array quick stats
     */
    public function get_stats() {
        return $this->stats;
    }

    /**
     * Get current migration version
     *
     * @return null if migration haven't been made, int if it does.
     */
    public function get_current_version() {
        $migration_path = $this->setting['info'];

        $current = null;
        if(file_exists($migration_path)) {
            $migrate = file_get_contents($migration_path);
            $migrate = json_decode($migrate, true);
            if(array_key_exists('version', $migrate)) {
                $current = $migrate['version'];
            }
        }

        return $current;
    }


    /**
     * Destroy current migration info
     *
     * @return void
     */
    public function reset_version() {
        $this->load_setting();
        $migration_path = $this->setting['info'];
        if(\file_exists($migration_path)) {
            \unlink($migration_path);
        }
        $this->stats = [];
    }


    function initRoutes() {
        $this->f3->route(array(
            'GET /migrations'
        ), 'DB\MIGRATIONS\Migrations->showHome');

        $this->f3->route(array(
            'GET /migrations/@action',
            'GET /migrations/@action/@target'
        ), 'DB\MIGRATIONS\Migrations->doIt');

        // this route will help if you have stored the UI dir in non-web-accessible path
        // the route works if plugin works (DEBUG>=3), so there is no security or performance concern
        $this->f3->route('GET /migrations/theme/@type/@file',
            function($f3, $args) {
                $web = \Web::instance();
                $file = $f3->UI.'migrations/theme/'.$args['type'].'/'.$args['file'];
                $mime = $web->mime($file);

                header('Content-Type: '.$mime);
                echo $f3->read($file);
            }
        );
    }


}