<?php

namespace bbn\Entities\Models\Internals;

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

trait EntityObjects
{
  protected array $objects = [];
  public function changes(): Changes
  {
    if (!isset($this->objects['changes'])) {
      $this->objects['changes'] = $this->entities->changes($this);
    }

    return $this->objects['changes'];
  }


  public function documents(): Document
  {
    if (!isset($this->objects['documents'])) {
      $this->objects['documents'] = $this->entities->documents($this);
    }

    return $this->objects['documents'];
  }


  public function documentRequests(): DocumentRequest
  {
    if (!isset($this->objects['documentRequests'])) {
      $this->objects['documentRequests'] = $this->entities->documentRequests($this);
    }

    return $this->objects['documentRequests'];
  }


  public function followers(): Follower
  {
    if (!isset($this->objects['followers'])) {
      $this->objects['followers'] = $this->entities->followers($this);
    }

    return $this->objects['followers'];
  }


  public function links(): Link
  {
    if (!isset($this->objects['links'])) {
      $this->objects['links'] = $this->entities->links($this);
    }

    return $this->objects['links'];
  }



  public function memberMessages(): MemberMessage
  {
    if (!isset($this->objects['memberMessages'])) {
      $this->objects['memberMessages'] = $this->entities->memberMessages($this);
    }

    return $this->objects['memberMessages'];
  }


  public function emailLinks(): EmailLink
  {
    if (!isset($this->objects['emailLinks'])) {
      $this->objects['emailLinks'] = $this->entities->emailLinks($this);
    }

    return $this->objects['emailLinks'];
  }


  public function noteLinks(): NoteLink
  {
    if (!isset($this->objects['noteLinks'])) {
      $this->objects['noteLinks'] = $this->entities->noteLinks($this);
    }

    return $this->objects['noteLinks'];
  }


  public function taskLinks(): TaskLink
  {
    if (!isset($this->objects['taskLinks'])) {
      $this->objects['taskLinks'] = $this->entities->taskLinks($this);
    }

    return $this->objects['taskLinks'];
  }


  public function documentRequestTypes(): DocumentRequestTypes
  {
    if (!isset($this->objects['documentRequestTypes'])) {
      $this->objects['documentRequestTypes'] = $this->entities->documentRequestTypes($this);
    }

    return $this->objects['documentRequestTypes'];
  }

  public function consultations(): Consultation
  {
    if (!isset($this->objects['consultations'])) {
      $this->objects['consultations'] = $this->entities->consultations($this);
    }

    return $this->objects['consultations'];
  }

}