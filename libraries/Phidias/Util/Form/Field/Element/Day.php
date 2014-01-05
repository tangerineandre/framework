<?php
namespace Phidias\Util\Form\Field\Element;

use Phidias\Util\Form\Field\Element;

class Day extends Element
{
    public function toHTML()
    {
        if ($this->value) {
            $selectedYear   = date('Y', $this->value);
            $selectedMonth  = date('n', $this->value);
            $selectedDay    = date('d', $this->value);
        } else {
            $selectedYear   = NULL;
            $selectedMonth  = NULL;
            $selectedDay    = NULL;
        }

        $startYear = $this->getOption('startYear', 1930);
        $endYear   = $this->getOption('endYear', date('Y'));

        if ($startYear > $endYear) {
            $endYear = $startYear;
        }

        $retval = "<select name=\"$this->name[Year]\">";
        $retval .= "<option value=\"\">---</option>";

        for ($year = $endYear; $year >= $startYear; $year--) {
            $selected = $selectedYear == $year ? 'selected="selected"' : '';
            $retval .= "<option value=\"$year\" $selected>$year</option>";
        }
        $retval .= "</select>";

        $retval .= " - ";

        $retval .= "<select name=\"$this->name[Month]\">";
        $retval .= "<option value=\"\">---</option>";

        for ($month = 1; $month <= 12; $month++) {
            $selected = $selectedMonth == $month ? 'selected="selected"' : '';
            $monthName = date('F', mktime(0, 0, 0, $month, 1, 1970));
            $retval .= "<option value=\"$month\" $selected>$monthName</option>";
        }
        $retval .= "</select>";

        $retval .= " - ";

        $retval .= "<select name=\"$this->name[Day]\">";
        $retval .= "<option value=\"\">---</option>";

        for ($day = 1; $day <= 31; $day++) {
            $selected = $selectedDay == $day ? 'selected="selected"' : '';
            $retval .= "<option value=\"$day\" $selected>$day</option>";
        }
        $retval .= "</select>";

        return $retval;
    }

    public function filter($input)
    {
        if (!isset($input['Year']) || !isset($input['Month']) || !isset($input['Day'])
            || !$input['Year'] || !$input['Month'] || !$input['Day']) {
            return NULL;
        }

        return mktime(0, 0, 0, $input['Month'], $input['Day'], $input['Year']);
    }
}