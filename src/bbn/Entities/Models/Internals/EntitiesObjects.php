<?php

namespace bbn\Entities\Models\Internals;

use Exception;
use bbn\X;
use bbn\Entities\Entity;
use bbn\Entities\Tables\Changes;
use bbn\Entities\Tables\Document;
use bbn\Entities\Tables\DocumentRequest;
use bbn\Entities\Tables\Follower;
use bbn\Entities\Tables\Link;
use bbn\Entities\Tables\MemberMessage;
use bbn\Entities\Junctions\Consultation;
use bbn\Entities\Junctions\DocumentRequestTypes;
use bbn\Entities\Junctions\EmailLink;
use bbn\Entities\Junctions\NoteLink;
use bbn\Entities\Junctions\OptionLink;
use bbn\Entities\Junctions\TaskLink;


trait EntitiesObjects
{
  protected array $objects = [];
  private static $classes = [];

  public function changes(?Entity $entity = null): Changes
  {
    return $this->factorEntityObject(__METHOD__, Changes::class, $entity);
  }

  public function documents(?Entity $entity = null): Document
  {
    return $this->factorEntityObject(__METHOD__, Document::class, $entity);
  }

  public function documentRequests(?Entity $entity = null): DocumentRequest
  {
    return $this->factorEntityObject(__METHOD__, DocumentRequest::class, $entity);
  }

  public function followers(?Entity $entity = null): Follower
  {
    return $this->factorEntityObject(__METHOD__, Follower::class, $entity);
  }

  public function links(?Entity $entity = null): Link
  {
    return $this->factorEntityObject(__METHOD__, Link::class, $entity);
  }

  public function memberMessages(?Entity $entity = null): MemberMessage
  {
    return $this->factorEntityObject(__METHOD__, MemberMessage::class, $entity);
  }

  public function consultations(?Entity $entity = null): Consultation
  {
    return $this->factorEntityObject(__METHOD__, Consultation::class, $entity);
  }

  public function documentRequestTypes(?Entity $entity = null): DocumentRequestTypes
  {
    return $this->factorEntityObject(__METHOD__, DocumentRequestTypes::class, $entity);
  }

  public function emailLinks(?Entity $entity = null): EmailLink
  {
    return $this->factorEntityObject(__METHOD__, EmailLink::class, $entity);
  }

  public function noteLinks(?Entity $entity = null): NoteLink
  {
    return $this->factorEntityObject(__METHOD__, NoteLink::class, $entity);
  }

  public function optionLinks(?Entity $entity = null): OptionLink
  {
    return $this->factorEntityObject(__METHOD__, OptionLink::class, $entity);
  }

  public function taskLinks(?Entity $entity = null): TaskLink
  {
    return $this->factorEntityObject(__METHOD__, TaskLink::class, $entity);
  }
}