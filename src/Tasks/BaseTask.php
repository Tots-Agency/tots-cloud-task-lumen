<?php

namespace Tots\CloudTask\Tasks;

interface BaseTask
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function run($params);
}