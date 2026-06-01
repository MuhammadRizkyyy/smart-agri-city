<?php

namespace App\Validators;

class LandValidator
{
    public static function validate($data)
    {
        if (
            empty($data['farmer_id']) ||
            empty($data['name']) ||
            empty($data['area_ha']) ||
            empty($data['soil_type']) ||
            !isset($data['lat']) ||
            !isset($data['lng'])
        ) {
            return false;
        }

        if ($data['area_ha'] <= 0) {
            return false;
        }

        if ($data['lat'] < -90 || $data['lat'] > 90) {
            return false;
        }

        if ($data['lng'] < -180 || $data['lng'] > 180) {
            return false;
        }

        return true;
    }
}