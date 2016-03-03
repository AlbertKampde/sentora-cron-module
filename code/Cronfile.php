<?php

/**
 * Class Cronfile
 * @author Albert Campderrós <albertkampde@gmail.com>
 */
class Cronfile
{
    /**
     * @return bool
     */
    public function writeToFile()
    {
        global $zdbh;
        $currentuser = ctrl_users::GetUserDetail();
        $line = "";
        $sql = "SELECT * FROM x_cronjobs WHERE ct_deleted_ts IS NULL";
        $numrows = $zdbh->query($sql);

        //common header whatever there are some cron task or not
        if (sys_versions::ShowOSPlatformVersion() != "Windows") {
            $line .= 'SHELL=/bin/bash' . fs_filehandler::NewLine();
            $line .= 'PATH=/sbin:/bin:/usr/sbin:/usr/bin' . fs_filehandler::NewLine();
            $line .= 'HOME=/' . fs_filehandler::NewLine();
            $line .= fs_filehandler::NewLine();
        }
        $restrictinfos = ctrl_options::GetSystemOption('php_exer') .
            " -d suhosin.executor.func.blacklist=\"passthru, show_source, shell_exec, system, pcntl_exec, popen, pclose, proc_open, proc_nice, proc_terminate, proc_get_status, proc_close, leak, apache_child_terminate, posix_kill, posix_mkfifo, posix_setpgid, posix_setsid, posix_setuid, escapeshellcmd, escapeshellarg, exec\" -d open_basedir=\"" . ctrl_options::GetSystemOption('hosted_dir') . $currentuser['username'] . "/"
            . ctrl_options::GetSystemOption('openbase_seperator') . ctrl_options::GetSystemOption('openbase_temp')
            . "\" ";

        $line .= "#################################################################################" . fs_filehandler::NewLine();
        $line .= "# CRONTAB FOR SENTORA CRON MANAGER MODULE                                        " . fs_filehandler::NewLine();
        $line .= "# Module Developed by Bobby Allen, 17/12/2009                                    " . fs_filehandler::NewLine();
        $line .= "# File automatically generated by Sentora " . sys_versions::ShowSentoraVersion() . fs_filehandler::NewLine();
        if (sys_versions::ShowOSPlatformVersion() == "Windows") {
            $line .= "# Cron Debug infomation can be found in file C:\WINDOWS\System32\crontab.txt " . fs_filehandler::NewLine();
            $line .= "#################################################################################" . fs_filehandler::NewLine();
            $line .= "" . ctrl_options::GetSystemOption('daemon_timing') . " " . $restrictinfos . ctrl_options::GetSystemOption('daemon_exer') . fs_filehandler::NewLine();
        }
        $line .= "#################################################################################" . fs_filehandler::NewLine();
        $line .= "# NEVER MANUALLY REMOVE OR EDIT ANY OF THE CRON ENTRIES FROM THIS FILE,          " . fs_filehandler::NewLine();
        $line .= "#  -> USE SENTORA INSTEAD! (Menu -> Advanced -> Cron Manager)                    " . fs_filehandler::NewLine();
        $line .= "#################################################################################" . fs_filehandler::NewLine();

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
                    //$line .= "# CRON ID: " . $rowcron['ct_id_pk'] . fs_filehandler::NewLine();
                    $line .= $rowcron['ct_timing_vc'] . " " . $restrictinfos . $rowcron['ct_fullpath_vc'] . fs_filehandler::NewLine();
                    //$line .= "# END CRON ID: " . $rowcron['ct_id_pk'] . fs_filehandler::NewLine();
                }
            }
        }
        if (fs_filehandler::UpdateFile(ctrl_options::GetSystemOption('cron_file'), 0644, $line)) {
            if (sys_versions::ShowOSPlatformVersion() != "Windows") {
                $returnValue = ctrl_system::systemCommand(
                    ctrl_options::GetSystemOption('zsudo'), array(
                        ctrl_options::GetSystemOption('cron_reload_command'),
                        ctrl_options::GetSystemOption('cron_reload_flag'),
                        ctrl_options::GetSystemOption('cron_reload_user'),
                        ctrl_options::GetSystemOption('cron_reload_path'),
                    )
                );
            }
            return true;
        } else {
            return false;
        }
    }
}