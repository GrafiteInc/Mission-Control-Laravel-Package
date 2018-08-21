<?php

namespace Grafite\MissionControl\Services;

class PerformanceAnalyzer
{
    public function getCpu()
    {
        $stats1 = $this->getCoreInformation();
        sleep(1);
        $stats2 = $this->getCoreInformation();

        $cpu = $this->getCpuPercentages($stats1, $stats2);

        $cpuState = $cpu['cpu0']['user'] + $cpu['cpu0']['nice'] + $cpu['cpu0']['sys'];

        if ($cpuState < 0) {
            return 0;
        }

        return $cpuState;
    }

    public function getMemory()
    {
        $free = shell_exec('free');
        $free = (string) trim($free);

        $free_arr = explode("\n", $free);

        $mem = explode(" ", $free_arr[1]);
        $mem = array_filter($mem);
        $mem = array_merge($mem);

        $memory_usage = $mem[2] / $mem[1] * 100;

        if ($memory_usage == 'NAN') {
            $memory_usage = 0;
        }

        return round($memory_usage);
    }

    public function getStorage()
    {
        return round((disk_free_space('/') / disk_total_space('/')) * 100);
    }

    public function getCoreInformation()
    {
        $data = file('/proc/stat');
        $cores = [];
        foreach ($data as $line) {
            if (preg_match('/^cpu[0-9]/', $line)) {
                $info = explode(' ', $line);
                $cores[] = [
                    'user' => $info[1],
                    'nice' => $info[2],
                    'sys' => $info[3],
                    'idle' => $info[4],
                ];
            }
        }
        return $cores;
    }

    public function getCpuPercentages($stat1, $stat2)
    {
        if (count($stat1) !== count($stat2)) {
            return;
        }

        $cpus = [];

        for ($i = 0, $l = count($stat1); $i < $l; $i++) {
            $dif = [];
            $dif['user'] = $stat2[$i]['user'] - $stat1[$i]['user'];
            $dif['nice'] = $stat2[$i]['nice'] - $stat1[$i]['nice'];
            $dif['sys'] = $stat2[$i]['sys'] - $stat1[$i]['sys'];
            $dif['idle'] = $stat2[$i]['idle'] - $stat1[$i]['idle'];
            $total = array_sum($dif);
            $cpu = [];

            foreach ($dif as $x => $y) {
                $cpu[$x] = round($y / $total * 100, 1);
            }

            $cpus['cpu' . $i] = $cpu;
        }

        return $cpus;
    }
}
