<?php

namespace Integration\DataManager;


interface DataProviderInterface
{
    /**
     * @param array $request
     * @return array
     */
    public function get(array $request);
}