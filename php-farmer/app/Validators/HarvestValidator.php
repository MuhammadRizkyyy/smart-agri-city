<?php

namespace App\Validators;

class HarvestValidator
{
    public static function validate($data)
    {
        if (
            empty($data['land_id']) ||
            empty($data['crop_type']) ||
            !isset($data['yield_ton']) ||
            empty($data['harvest_date'])
        ) {
            return false;
        }

        if ($data['yield_ton'] <= 0) {
            return false;
        }

        return true;
    }
}