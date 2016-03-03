<?php

/**
 * Class Cronfile
 * @author Albert Campderrós <albertkampde@gmail.com>
 */
class Cronfile
{
    /**
     * @var string
     */
    protected $osPlatformVersion;

    /**
     * @var string
     */
    protected $sentoraVersion;


    public function __construct()
    {
        $this->osPlatformVersion = sys_versions::ShowOSPlatformVersion();
        $this->sentoraVersion = sys_versions::ShowSentoraVersion();
    }

    /**
     * @return bool
     */
    public function writeToFile()
    {
        global $zdbh;
        $user = ctrl_users::GetUserDetail();
        $line = "";
        $sql = "SELECT * FROM x_cronjobs WHERE ct_deleted_ts IS NULL";
        $numrows = $zdbh->query($sql);

        //common header whatever there are some cron task or not
        if ($this->getOsPlatformVersion() != "Windows") {
            $line .= 'SHELL=/bin/bash' . $this->newLine();
            $line .= 'PATH=/sbin:/bin:/usr/sbin:/usr/bin' . $this->newLine();
            $line .= 'HOME=/' . $this->newLine();
            $line .= $this->newLine();
        }

        $line .= $this->getCronFileHeader($user);

        //Write command lines in crontab, if any
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $sql->execute();
            while ($rowcron = $sql->fetch()) {
                $fetchRows = $zdbh->prepare("SELECT * FROM x_accounts WHERE ac_id_pk=:userid AND ac_deleted_ts IS NULL");
                $fetchRows->bindParam(':userid', $rowcron['ct_acc_fk']);
                $fetchRows->execute();
                $rowclient = $fetchRows->fetch();
                if ($rowclient && $rowclient['ac_enabled_in'] <> 0) {
                    $line .= $rowcron['ct_timing_vc'] . " " . $this->getRestrictions($rowclient['ac_user_vc'])
                        . $rowcron['ct_fullpath_vc']
                        . " > " . $this->getSystemOption('hosted_dir') . $rowclient['ac_user_vc'] . "/logs/cron." . $rowcron['ct_id_pk'] . ".log 2>&1"
                        . $this->newLine();
                }
            }
        }

        if (fs_filehandler::UpdateFile($this->getSystemOption('cron_file'), 0644, $line)) {
            if ($this->getOsPlatformVersion() != "Windows") {
                $returnValue = ctrl_system::systemCommand(
                    $this->getSystemOption('zsudo'), array(
                        $this->getSystemOption('cron_reload_command'),
                        $this->getSystemOption('cron_reload_flag'),
                        $this->getSystemOption('cron_reload_user'),
                        $this->getSystemOption('cron_reload_path'),
                    )
                );
            }

            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    protected function newLine()
    {
        return fs_filehandler::NewLine();
    }

    /**
     * @return string
     */
    protected function getOsPlatformVersion()
    {
        return $this->osPlatformVersion;
    }

    /**
     * @param string $option
     * @return mixed
     */
    protected function getSystemOption($option)
    {
        return ctrl_options::GetSystemOption($option);
    }

    /**
     * @param array $user
     * @return string
     */
    protected function getCronFileHeader($user)
    {
        $line = "#################################################################################" . $this->newLine();
        $line .= "# CRONTAB FOR SENTORA CRON MANAGER MODULE                                        " . $this->newLine();
        $line .= "# File automatically generated by Sentora " . $this->sentoraVersion . $this->newLine();
        if ($this->getOsPlatformVersion() == "Windows") {
            $line .= "# Cron Debug infomation can be found in file C:\WINDOWS\System32\crontab.txt " . $this->newLine();
            $line .= "#################################################################################" . $this->newLine();
            $line .= "" . $this->getSystemOption('daemon_timing') . " " . $this->getRestrictions($user['username']) . $this->getSystemOption('daemon_exer') . $this->newLine();
        }
        $line .= "#################################################################################" . $this->newLine();
        $line .= "# NEVER MANUALLY REMOVE OR EDIT ANY OF THE CRON ENTRIES FROM THIS FILE,          " . $this->newLine();
        $line .= "#  -> USE SENTORA INSTEAD! (Menu -> Advanced -> Cron Manager)                    " . $this->newLine();
        $line .= "#################################################################################" . $this->newLine();

        return $line;
    }

    /**
     * @param string $username
     * @return string
     */
    protected function getRestrictions($username)
    {
        return $this->getSystemOption('php_exer') .
            " -d suhosin.executor.func.blacklist=\"passthru, show_source, shell_exec, system, pcntl_exec, popen, pclose, proc_open, proc_nice, proc_terminate, proc_get_status, proc_close, leak, apache_child_terminate, posix_kill, posix_mkfifo, posix_setpgid, posix_setsid, posix_setuid, escapeshellcmd, escapeshellarg, exec\" -d open_basedir=\""
            . $this->getSystemOption('hosted_dir') . $username . "/"
            . $this->getSystemOption('openbase_seperator') . $this->getSystemOption('openbase_temp')
            . "\" ";
    }
}