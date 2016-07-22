<?php
namespace Utils;


class Pokemon {
    private $meta = null;

    public function __construct($meta) {
        $this->meta = $meta;
    }

    public function getLocation() {
        return [
            'latitude' => $this->meta['latitude'],
            'longitude' => $this->meta['longitude']
        ];
    }

    public function getId() {
        return $this->meta['pokemon_id'];
    }

    public function getName() {
        return $this->meta['pokemon_name'];
    }

    public function getExpiresTimestamp() {
        return $this->meta['expires'];
    }

    public function getExpires() {
        $date = new DateTime();
        $date->setTimestamp($this->getExpiresTimestamp());
        return $date;
    }

    public function __toString() {
        $location = $this->getLocation();

        return sprintf(
            "%s [%d]: %f, %f, %d seconds left",
            $this->getName(),
            $this->getId(),
            $location['latitude'],
            $location['longitude'],
            $this->getExpiresTimestamp() - time()
        );
    }
}