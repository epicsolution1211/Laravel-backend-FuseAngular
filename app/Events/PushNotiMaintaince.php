<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PushNotiMaintaince implements ShouldBroadcast
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public $message;

  public function __construct($message)
  {
      $this->message = $message;
  }

  public function broadcastOn()
  {
      return ['my-channel'];
  }

  public function broadcastAs()
  {
      return 'my-event';
  }
  /*public $actionId;
  public $actionData;

  public function __construct($actionId, $actionData)
  {
      $this->actionId = $actionId;
      $this->actionData = $actionData;
  }

  public function broadcastOn()
  {
      return new Channel('my-channel');
  }

  public function broadcastAs()
  {
      return 'my-event';
  }

  public function broadcastWith()
  {
      return [
          'actionId' => $this->actionId,    
          'actionData' => $this->actionData,
      ];
  }*/
}
