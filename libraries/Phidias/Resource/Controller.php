<?php

namespace Phidias\Resource;

use Phidias\HashTable;

/**
* Resource Controller
*/
class Controller
{
    protected $attributes;  //Resource\Attributes
    protected $arguments;   //HashTable
    protected $request;     //Resource\Request
    protected $response;    //Resource\Response

    public function setAttributes($attributes)
    {
        $this->attributes = new Attributes($attributes);
        return $this;
    }

    public function setArguments($arguments)
    {
        $this->arguments = new HashTable($arguments);
        return $this;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    public function setResponse(Response $response)
    {
        $this->response = $response;
        return $this;
    }


    public function example(/*$personID, $argument2, $input ($input is always appended to the arguments)*/)
    {
        /* attributes: */
        $limit  = $this->attributes->get('limit', 10);
        $others = $this->attributes->except('limit');
        $uhu    = $this->attributes->has('debug');
        $all    = $this->attributes->all();

        /* arguments: */
        $personID = $this->arguments->get('personID');


        /* request data: 
        The Environment object is responsible for parsing the request body into a data object
        The resulting data is available via:
        */
        $data = $this->request->data;

        /* It is ALSO available in the last argument of this function's invocation: */
        $data = $input; //see function call


        /* The request's raw input can still be accessed via.  This is the STRING sent as postdata in the HTTP request */
        $data = $this->request->body;

        /* With some additional properties from the request: */
        $this->request->method;
        $this->request->URI;
        $this->request->contentType;
        $this->request->headers->getAll();
        $this->request->headers->get('accept');


        /* Aspects of the response can be set invoking: */
        $this->response->code    = 201;
        $this->response->message = 'created';
        $this->response->headers->set('X-Allow-Whatever', '123');


        /*
        Data model

        The data model is the controller's output.  It represents the result of whatever logic the controller implements.

        If this is the first controller executed for the resource, the model will be empty.
        The model can be set by assigning a value to $this->response->data,  or by returning a value:
        */
        $this->response->data = 'foo';

        // or

        return 'foo';

    }


    public function nestedResource($newPerson)
    {
        $subResource = new Resource('people');
        $response    = $subResource->post($newPerson);

        return $response->data;
    }

    public function nestedController($newPerson)
    {
        $subController = new Person\Controller;

        return $subController->postCollection($newPerson);
    }

}