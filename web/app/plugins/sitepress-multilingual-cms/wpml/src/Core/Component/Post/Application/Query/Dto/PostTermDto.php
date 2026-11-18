<?php

namespace WPML\Core\Component\Post\Application\Query\Dto;

class PostTermDto {

  private $id;

  private $name;

  private $ttid;

  private $parent;

  private $description;


  public function __construct(
    int $id,
    string $name,
    ?int $ttid = null,
    ?int $parent = null,
    string $description = ''
  ) {
    $this->id          = $id;
    $this->name        = $name;
    $this->ttid        = $ttid;
    $this->parent      = $parent;
    $this->description = $description;
  }


  public function getId(): int {
    return $this->id;
  }


  public function getName(): string {
    return $this->name;
  }


  public function getTtid(): ?int {
    return $this->ttid;
  }


  public function getParent(): ?int {
    return $this->parent;
  }


  public function getDescription(): string {
    return $this->description;
  }


}
