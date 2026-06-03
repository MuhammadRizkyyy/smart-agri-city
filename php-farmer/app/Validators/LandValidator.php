<?php

namespace App\Validators;

class LandValidator
{
    public static function validate(array $data): array
    {
        $errors = [];

        if (empty($data['farmer_id'])) {
            $errors['farmer_id'] = 'farmer_id is required.';
        } elseif (!is_numeric($data['farmer_id']) || (int)$data['farmer_id'] <= 0) {
            $errors['farmer_id'] = 'farmer_id must be a positive integer.';
        }

        if (empty($data['name'])) {
            $errors['name'] = 'Name is required.';
        } elseif (strlen($data['name']) > 100) {
            $errors['name'] = 'Name must not exceed 100 characters.';
        }

        if (!isset($data['area_ha']) || $data['area_ha'] === '') {
            $errors['area_ha'] = 'area_ha is required.';
        } elseif (!is_numeric($data['area_ha']) || (float)$data['area_ha'] <= 0) {
            $errors['area_ha'] = 'area_ha must be greater than 0.';
        }

        if (empty($data['soil_type'])) {
            $errors['soil_type'] = 'soil_type is required.';
        }

        if (!isset($data['lat']) || $data['lat'] === '') {
            $errors['lat'] = 'lat (latitude) is required.';
        } elseif (!is_numeric($data['lat']) || (float)$data['lat'] < -90 || (float)$data['lat'] > 90) {
            $errors['lat'] = 'lat must be a number between -90 and 90.';
        }

        if (!isset($data['lng']) || $data['lng'] === '') {
            $errors['lng'] = 'lng (longitude) is required.';
        } elseif (!is_numeric($data['lng']) || (float)$data['lng'] < -180 || (float)$data['lng'] > 180) {
            $errors['lng'] = 'lng must be a number between -180 and 180.';
        }

        return $errors;
    }
}
