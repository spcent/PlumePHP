<?php

namespace Plume\Libs;

/**
 * Processes the JSON-RPC input
 */
class JsonRpcServer
{
    const SPEC_1_0 = 8;             // 000 001 000
    const SPEC_2_0 = 16;            // 000 010 000
    /**
     * @var array The parsed json input as an associative array
     * @access private
     */
    private $input;
    /**
     * @var array A list of associative response arrays to json_encode
     * @access private
     */
    private $response;
    /**
     * The spec version the serve will use
     * @var int
     * @access private
     */
    public $spec = self::SPEC_2_0;
    /**
     * This is modified by Server::hideErrors()
     * @var bool
     * @access private
     */
    public $hide_errors = false;

    /**
     * Constructss a Server object
     */
    public function __construct() {}

    /**
     * Sets the spec version to use for this server
     * @param string $spec The spec version (e.g.: "2.0")
     */
    public function useSpec($spec)
    {
        $this->spec = self::validateSpecVersion($spec);
        return $this;
    }

    /**
     * Evaluates and returns the passed JSON-RPC spec version
     * @private
     * @param string $version spec version as a string (using semver notation)
     */
    protected static function validateSpecVersion($version)
    {
        switch ($version) {
        case '1.0':
            return self::SPEC_1_0;
            break;
        case '2.0':
            return self::SPEC_2_0;
        default:
            throw new \Exception('Unsupported spec version: ' + $version);
        }
    }

    /**
     * If invoked, the server will try to hide all PHP errors, to prevent them from obfuscating the output.
     */
    public function hideErrors()
    {
        $this->hide_errors = true;
        return $this;
    }

    /**
     * Starts processing of the HTTP input. This will stop further execution of the script.
     */
    public function dispatch()
    {
        // disable error reporting?
        if ($this->hide_errors) error_reporting(0);// prevents messing up the response
        $this->input = file_get_contents('php://input');

        // record request
        L('rpc request: '.$this->input);

        $json_errors = [
            JSON_ERROR_NONE => '',
            JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded',
            JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_SYNTAX => 'Syntax error'
        ];
        // set header if not already sent...
        if (headers_sent() === FALSE) header('Content-type: application/json');
        // any request at all?
        if (trim($this->input) === '') {
            $this->returnError(null, -32600);
            $this->respond();
        }
        // decode request...
        $this->input = json_decode($this->input, true);
        if($this->input === NULL) {
            $this->returnError(null, -32700, 'JSON parse error: '.$json_errors[json_last_error()] );
            $this->respond();
        }
        // batch?
        if (($batch = self::interpretBatch($this->input)) !== FALSE) {
            foreach($batch as $request) {
                $this->process($request);
            }
            $this->respond();
        }
        // process request
        $this->process($this->input);
        $this->respond();
    }

    /**
     * Processes the passed request
     * @param array $request the parsed request
     */
    public function process($request)
    {
        $server = $this;
        $params = (isset($request['params']) === FALSE) ? [] : $request['params'];
        $id = (isset($request['id']) === FALSE) ? null : $request['id'];
        $isNotific = self::interpretRequest($this->spec, $request) === FALSE;
        // utility closures
        $error = function($code, $msg='', $data=null) use ($server, $id, $isNotific) {
            if($isNotific) return;
            $server->returnError($id, $code, $msg, $data);
        };

        $result = function($result) use ($server, $id, $isNotific){
            if($isNotific) return;
            $server->returnResult($id, $result);
        };
        //validate...
        if (($req = self::interpretRequest($this->spec, $request)) === FALSE) {
            if (($req = self::interpretNotification($this->spec, $request)) === FALSE) {
                return $error($id, -32600, 'Invalid Request', $request);
            }
        }
        //invoke...
        try {
            $vo = new \PlumeViewObject();
            $vo->updateRouteArg($params);
            return $result(\PlumePHP::app()->biz($request['method'], $vo));
        } catch(\Exception $e) {
            $errMsg = ($e->getMessage() != "") ? $e->getMessage() : 'Internal error invoking method';
            $errCode = $e->getCode();
            if ($errCode != 1001 && $errCode != 4001) {
                L('rpc error: '.$errMsg.', method: '.$request['method'].', params: '.$vo, [], 'ERROR', true);
            }

            return $error(-32603, $errMsg, $e->getCode());
        }
    }

    /**
     * Receives the computed result
     * @param mixed $id The id of the original request
     * @param mixed $result The computed result
     * @access private
     */
    public function returnResult($id, $result)
    {
        switch($this->spec) {
        case self::SPEC_2_0:
            $this->response[] = [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result
            ];
            break;
        case self::SPEC_1_0:
            $this->response[] = [
                'id' => $id,
                'result' => $result,
                'error' => null
            ];
            break;
        }
    }

    /**
     * Receives the error from computing the result
     * @param mixed $id The id of the original request
     * @param integer $code The error code
     * @param string $message The error message
     * @param mixed $data Additional data
     * @access private
     */
    public function returnError($id, $code, $message='', $data=null)
    {
        $msg = [
            -32700 => 'Parse error',
            -32600 => 'Invalid Request',
            -32601 => 'Method not found',
            -32602 => 'Invalid params',
            -32603 => 'Internal error'
        ];

        switch($this->spec) {
        case self::SPEC_2_0:
            $response = [
                'jsonrpc'=>'2.0',
                'id'=>$id,
                'error'=>[
                    'code'=>$code,
                    'message'=>$message,
                    'data'=>$data
                ]
            ];
            break;
        case self::SPEC_1_0:
            $response = [
                'id'=>$id,
                'result'=>null,
                'error'=>[
                    'code'=>$code,
                    'message'=>$message,
                    'data'=>$data
                ]
            ];
            break;
        }

        if ($message === '') $response['error']['message'] = $msg[$code];
        $this->response[] = $response;
    }

    /**
     * Outputs the processed response
     * @access private
     */
    public function respond()
    {
        // no array
        if (!is_array($this->response)) return;

        $count = count($this->response);
        // single request
        if ($count == 1) {
            echo json_encode($this->response[0], JSON_UNESCAPED_UNICODE);
            return;
        }

        // batch request
        if ($count > 1) {
            echo json_encode($this->response, JSON_UNESCAPED_UNICODE);
            return;
        }

        // no response
        if($count < 1) return;
    }

    /**
     * Validates and sanitizes a normal request
     * @param array $assoc The json-parsed JSON-RPC request
     * @static
     * @return array Returns the sanitized request and if it was invalid, a boolean FALSE is returned
     */
    public static function interpretRequest($spec, array $assoc)
    {
        switch($spec) {
        case self::SPEC_2_0:
            if (isset($assoc['jsonrpc'], $assoc['id'], $assoc['method']) === FALSE) return FALSE;
            if ($assoc['jsonrpc'] != '2.0' || !is_string($assoc['method'])) return FALSE;
            $request = [
                'id' =>  &$assoc['id'],
                'method' => &$assoc['method']
            ];
            if (isset($assoc['params'])) {
                if (!is_array($assoc['params'])) return FALSE;
                $request['params'] = $assoc['params'];
            }
            return $request;
        case self::SPEC_1_0:
            if (isset($assoc['id'], $assoc['method']) === FALSE) return FALSE;
            if (!is_string($assoc['method'])) return FALSE;
            $request = [
                'id' =>  &$assoc['id'],
                'method' => &$assoc['method']
            ];
            if (isset($assoc['params'])) {
                if(!is_array($assoc['params']) || (bool)count(array_filter(array_keys($assoc['params']), 'is_string'))) return FALSE;// if not associative
                $request['params'] = &$assoc['params'];
            }
            return $request;
        }
    }

    /**
     * Validates and sanitizes a notification
     * @param array $assoc The json-parsed JSON-RPC request
     * @static
     * @return array Returns the sanitized request and if it was invalid, a boolean FALSE is returned
     */
    public static function interpretNotification($spec, array $assoc)
    {
        switch($spec) {
        case self::SPEC_2_0:
            if (isset($assoc['jsonrpc'], $assoc['method']) === FALSE || isset($assoc['id']) !== FALSE) return FALSE;
            if ($assoc['jsonrpc'] != '2.0' || !is_string($assoc['method'])) return FALSE;
            $request = ['method' => &$assoc['method']];
            if(isset($assoc['params'])) {
                if(!is_array($assoc['params'])) return FALSE;
                $request['params'] = $assoc['params'];
            }
            return $request;
        case self::SPEC_1_0:
            if (isset($assoc['method']) === FALSE || isset($assoc['id']) !== FALSE) return FALSE;
            if (!is_string($assoc['method'])) return FALSE;
            $request = [
                'method' => &$assoc['method']
            ];
            if(isset($assoc['params'])) {
                if(!is_array($assoc['params']) || (bool)count(array_filter(array_keys($assoc['params']), 'is_string'))) return FALSE;// if not associative
                $request['params'] = $assoc['params'];
            }
            return $request;
        }
    }

    /**
     * Validates a batch request
     * @param array $assoc The json-parsed JSON-RPC request
     * @static
     * @return array Returns the original request and if it was invalid, a boolean FALSE is returned
     * @access private
     */
    public static function interpretBatch(array $assoc)
    {
        if (count($assoc) <= 1) return FALSE;
        foreach($assoc as $req) {
            if(!is_array($req)) return FALSE;
        }
        return $assoc;
    }
}