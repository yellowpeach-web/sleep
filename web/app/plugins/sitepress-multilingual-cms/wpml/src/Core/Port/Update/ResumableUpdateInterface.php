<?php

namespace WPML\Core\Port\Update;

interface ResumableUpdateInterface extends UpdateInterface {


  public function isFinished();


  public function progress();


}
