<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Evidence;

class Manifest {

  const VERSION = 1;

  const KIND_NEW = 'new';

  const KIND_UPDATE = 'update';

  private $kind;

  private $words;

  private $tier;

  private $degradations;

  private $fingerprint;

  private $proof;

  private $proofBytes;


  public function __construct(
    $kind,
    $words,
    $tier,
    $degradations,
    $fingerprint,
    $proof,
    $proofBytes
  ) {
    $this->kind = $kind;
    $this->words = $words;
    $this->tier = $tier;
    $this->degradations = $degradations;
    $this->fingerprint = $fingerprint;
    $this->proof = $proof;
    $this->proofBytes = $proofBytes;
  }


  public function getKind(): string {
    return $this->kind;
  }


  public function getWords() {
    return $this->words;
  }


  public function getTier(): string {
    return $this->tier;
  }


  public function getDegradations() {
    return $this->degradations;
  }


  public function getFingerprint(): string {
    return $this->fingerprint;
  }


  public function getProof() {
    return $this->proof;
  }


  public function getProofBytes() {
    return $this->proofBytes;
  }


  public function toArray(): array {
    return [
      'version'      => self::VERSION,
      'kind'         => $this->kind,
      'words'        => $this->words,
      'tier'         => $this->tier,
      'degradations' => $this->degradations,
      'fingerprint'  => Fingerprint::HASH_ALGORITHM . ':' . $this->fingerprint,
      'proof_bytes'  => $this->proofBytes,
      'proof'        => $this->proof,
    ];
  }


}
