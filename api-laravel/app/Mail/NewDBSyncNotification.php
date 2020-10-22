<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use \Illuminate\Http\Client\Response;

class NewDBSyncNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The response instance.
     *
     * @var \Illuminate\Http\Client\Response
     */
    protected $response;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from("pj.dreamtv@gmail.com")
        ->subject("Kinoteka - DB Sync Completed")
        ->view('emails.synccompleted')
        ->with([
            'response' => $this->response,
        ]);
    }
}
