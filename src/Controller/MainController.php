<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends Controller
{
    private $data;

    /**
     * @Route("/")
     */
    public function number()
    {
        $this->getInput();

        foreach ($this->data as $key => $ele) {
            $info[$key]['id']       = $ele['id'];
            $info[$key]['weight']   = str_replace(',', '.', $ele['weight']);
            $info[$key]['days']     = $this->getInformation($ele);
        }

        return $this->render('main/index.html.twig', array('info' => $info));
    }

    private function getInput()
    {
        $path = $this->get('kernel')->getProjectDir().'/assets/input.csv';
        if (($fb = fopen ($path,"r")) !== FALSE) {
            $i = 0;
            while (($info = fgetcsv($fb, filesize($path), ";")) !== FALSE) {
                $this->data[$i]['id']     = $info[0];
                $this->data[$i]['date']   = $info[1];
                $this->data[$i]['type']   = $info[2];
                $this->data[$i]['weight'] = $info[3];
                $this->data[$i]['long']   = $info[4];
                $i++;
            }
            fclose ($fb);
        }
    }

    private function getInformation($data)
    {
        $from = $this->getType($data['date'], $data['type']);
        $to = new \DateTime($from);
        $to->modify('+'.$data['long'].' month');

        return $this->getNumberDaysInMonth($from, $to->format('Y-m-d'));
    }

    private function getType($date, $type)
    {
        $date = new \DateTime($date);
        if ($type == 2) {
            $date->modify('first day of next month');
        }
        $date->modify('first day of this month');

        if (in_array($date->format('m'), ['1', '5', '11']))
            $date->modify('+1 day');

        return $date->format('Y-m-d');
    }

    private function getNumberDaysInMonth($from, $to)
    {
        $workingDays = [1, 2, 3, 4, 5]; # date format = N (1 = Monday, ...)
        $holidayDays = ['*-01-01', '*-01-06','*-05-01','*-05-03','*-08-15','*-11-01','*-11-11','*-12-25','*-12-26']; # variable and fixed holidays

        $from = new \DateTime($from);
        $to = new \DateTime($to);
        $to->modify('+1 day');
        $interval = new \DateInterval('P1D');
        $periods = new \DatePeriod($from, $interval, $to);

        $fromYear = $from->format('Y');

        // added movement holidays in year
        for ($i = 0; $i <= $from->diff($to)->format('%y'); $i++) {
            // easter
            $easter = date('Y-m-d', strtotime('+1 day', easter_date($fromYear + $i)));
            $holidayDays[] = $easter;
            // easter monday
            $holidayDays[] = date('Y-m-d', strtotime('+1 day', strtotime($easter) ));
            // corpus christi
            $holidayDays[] = date('Y-m-d', strtotime('+60 days', strtotime($easter) ));
            // pentecost
            $holidayDays[] = date('Y-m-d', strtotime('+49 days', strtotime($easter) ));
        }

        $days = 0; $holidays = 0; $saturdays = 0; $sundays = 0;
        foreach ($periods as $period) {
            if ($period->format('N') == 6) {
                $saturdays++;
                continue;
            }
            if ($period->format('N') == 7) {
                $sundays++;
                continue;
            }
            if (in_array($period->format('Y-m-d'), $holidayDays)) {
                $holidays++;
                continue;
            }
            if (in_array($period->format('*-m-d'), $holidayDays)) {
                $holidays++;
                continue;
            }
            $days++;
        }

        return ['workdays' => $days, 'holidays' => $holidays, 'saturdays' => $saturdays, 'sundays' => $sundays];
    }
}