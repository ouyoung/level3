<?php
namespace Level3\Tests;

use Level3\Level3;
use Level3\Processor\Wrapper\CrossOriginResourceSharing as CORS;
use Level3\Messages\Response;
use Teapot\StatusCode;

class CrossOriginResourceSharingTest extends TestCase
{
    public function createWrapper()
    {
        $wrapper = new CORS();
        $wrapper->setAllowMethods(false);
        $wrapper->setAllowOrigin(null);

        return $wrapper;
    }

    protected function createRequestMockWithGetHeader($header = null, $value = null)
    {
        $request = $this->createRequestMockSimple();
        if ($header) {
            $request->headers->shouldReceive('get')
            ->with($header)->once()->andReturn($value);
        }

        return $request;
    }

    protected function callGetInWrapperAndGetResponse($method, $wrapper, $request = null, $response = null)
    {
        if (!$request) $request = $this->createRequestMockSimple();
        if (!$response) $response = new Response();

        $repository = $this->createRepositoryMock();

        return $wrapper->$method(
            $repository, $request,
            function ($repository, $request) use ($response) {
                return $response;
            }
        );
    }

    public function testOptions()
    {
        $wrapper = $this->createWrapper();
        $response = $this->callGetInWrapperAndGetResponse('options', $wrapper);

        $this->assertSame(StatusCode::NO_CONTENT, $response->getStatusCode());
    }

    public function testSetAllowOriginWildcard()
    {
        $wrapper = $this->createWrapper();
        $wrapper->setAllowOrigin(CORS::ALLOW_ORIGIN_WILDCARD);

        $this->assertSame(CORS::ALLOW_ORIGIN_WILDCARD, $wrapper->getAllowOrigin());

        $request = $this->createRequestMockWithGetHeader();
        $response = $this->callGetInWrapperAndGetResponse('get', $wrapper, $request);
        $this->assertSame(CORS::ALLOW_ORIGIN_WILDCARD, $response->headers->get(CORS::HEADER_ALLOW_ORIGIN));
    }

    public function testSetAllowOriginHost()
    {
        $url = 'http://foo.bar';
        $wrapper = $this->createWrapper();
        $wrapper->setAllowOrigin($url);

        $this->assertSame($url, $wrapper->getAllowOrigin());

        $request = $this->createRequestMockWithGetHeader(CORS::HEADER_ORIGIN, $url);
        $response = $this->callGetInWrapperAndGetResponse('get', $wrapper, $request);
        $this->assertSame($url, $response->headers->get(CORS::HEADER_ALLOW_ORIGIN));
    }

    /**
     * @expectedException Level3\Exceptions\Forbidden
     */
    public function testReadOriginInvalid()
    {
        $url = 'http://foo.bar';
        $requestUrl = 'http://baz.qux';

        $wrapper = $this->createWrapper();
        $wrapper->setAllowOrigin($url);

        $request = $this->createRequestMockWithGetHeader(CORS::HEADER_ORIGIN, $requestUrl);
        $this->callGetInWrapperAndGetResponse('get', $wrapper, $request);
    }

    public function testReadOriginInvalidWithErrorMethod()
    {
        $url = 'http://foo.bar';
        $requestUrl = 'http://baz.qux';

        $wrapper = $this->createWrapper();
        $wrapper->setAllowOrigin($url);

        $request = $this->createRequestMockWithGetHeader(CORS::HEADER_ORIGIN, $requestUrl);
        $this->callGetInWrapperAndGetResponse('error', $wrapper, $request);
    }

    public function testReadOriginNone()
    {
        $allowOrigin = '*';

        $wrapper = $this->createWrapper();
        $wrapper->setAllowOrigin($allowOrigin);

        $request = $this->createRequestMockWithGetHeader();
        $response = $this->callGetInWrapperAndGetResponse('get', $wrapper, $request);
        $this->assertSame($allowOrigin, $response->headers->get(CORS::HEADER_ALLOW_ORIGIN));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testSetAllowOriginNotValidHost()
    {
        $url = 'foobar';
        $wrapper = $this->createWrapper();
        $wrapper->setAllowOrigin($url);
    }

    public function testSetMultipleAllowOrigin()
    {
        $urls = [
            'http://foo.bar',
            'http://qux.baz'
        ];

        $wrapper = $this->createWrapper();
        $wrapper->setMultipleAllowOrigin($urls);

        $this->assertSame($urls, $wrapper->getAllowOrigin());

        $request = $this->createRequestMockWithGetHeader(CORS::HEADER_ORIGIN, $urls[0]);
        $response = $this->callGetInWrapperAndGetResponse('get', $wrapper, $request);
        $this->assertSame($urls, $response->headers->get(CORS::HEADER_ALLOW_ORIGIN, null, false));
    }

    public function testSetExposeHeaders()
    {
        $headers = ['bar', 'Foo'];

        $wrapper = $this->createWrapper();
        $wrapper->setExposeHeaders($headers);

        $this->assertSame(array_map('strtolower', $headers), $wrapper->getExposeHeaders());

        $response = new Response();
        $response->headers->set('foo', 'qux');
        $response->headers->set('Bar', 'qux');
        $response->headers->set('Qux', 'qux');

        $this->callGetInWrapperAndGetResponse('get', $wrapper, null, $response);
        $this->assertSame('Bar, Foo', $response->headers->get(CORS::HEADER_EXPOSE_HEADERS));
    }

    public function testSetExposeHeadersDefault()
    {
        $wrapper = $this->createWrapper();

        $response = new Response();
        $response->headers->set('foo', 'qux');
        $response->headers->set('bar', 'baz');

        $this->callGetInWrapperAndGetResponse('get', $wrapper, null, $response);
        $this->assertSame('Foo, Bar', $response->headers->get(CORS::HEADER_EXPOSE_HEADERS));
    }

    public function testSetMaxAge()
    {
        $maxAge = 100;

        $wrapper = $this->createWrapper();
        $wrapper->setMaxAge($maxAge);

        $this->assertSame($maxAge, $wrapper->getMaxAge());

        $response = $this->callGetInWrapperAndGetResponse('options', $wrapper);
        $this->assertSame($maxAge, $response->headers->get(CORS::HEADER_MAX_AGE));

        $response = $this->callGetInWrapperAndGetResponse('get', $wrapper);
        $this->assertNull($response->headers->get(CORS::HEADER_MAX_AGE));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testSetMaxAgeNotANumber()
    {
        $invalidAge = 'foobar';
        $wrapper = $this->createWrapper();
        $wrapper->setMaxAge($invalidAge);
    }

    public function testSetAllowCredentialsTrue()
    {
        $allow = true;

        $wrapper = $this->createWrapper();
        $wrapper->setAllowCredentials($allow);

        $this->assertSame($allow, $wrapper->getAllowCredentials());

        $response = $this->callGetInWrapperAndGetResponse('get', $wrapper);
        $this->assertSame('true', $response->headers->get(CORS::HEADER_ALLOW_CRENDENTIALS));
    }

    public function testSetAllowCredentialsFalse()
    {
        $allow = false;

        $wrapper = $this->createWrapper();
        $wrapper->setAllowCredentials($allow);

        $this->assertSame($allow, $wrapper->getAllowCredentials());

        $response = $this->callGetInWrapperAndGetResponse('get', $wrapper);
        $this->assertSame('false', $response->headers->get(CORS::HEADER_ALLOW_CRENDENTIALS));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testSetAllowCredentialsNonBoolean()
    {
        $invalidAllow = 'foobar';
        $wrapper = $this->createWrapper();
        $wrapper->setAllowCredentials($invalidAllow);
    }

    public function testSetAllowMethods()
    {
        $methods = ['bar', 'foo'];

        $wrapper = $this->createWrapper();
        $wrapper->setAllowMethods(true);

        $repository = $this->createRepositoryMock();

        $mapper = $this->getMock('Level3\Mapper');
        $mapper->expects($this->once())
            ->method('getMethods')
            ->will($this->returnValue($methods));

        $level3 = $this->createLevel3Mock();
        $level3->shouldReceive('getMapper')->once()->andReturn($mapper);

        $this->assertSame(true, $wrapper->getAllowMethods());
        $wrapper->setLevel3($level3);

        $request = $this->createRequestMock();

        $response = $this->callGetInWrapperAndGetResponse('options', $wrapper, $request);
        $this->assertSame('bar, foo', $response->headers->get(CORS::HEADER_ALLOW_METHODS));

        $response = $this->callGetInWrapperAndGetResponse('get', $wrapper, $request);
        $this->assertNull($response->headers->get(CORS::HEADER_ALLOW_METHODS));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testSetAllowMethodsInvalid()
    {
        $wrapper = $this->createWrapper();
        $wrapper->setAllowMethods(1);
    }

    public function testSetAllowHeaders()
    {
        $headers = ['bar', 'foo'];

        $wrapper = $this->createWrapper();
        $wrapper->setAllowHeaders($headers);

        $this->assertSame($headers, $wrapper->getAllowHeaders());

        $response = $this->callGetInWrapperAndGetResponse('options', $wrapper);
        $this->assertSame('bar, foo', $response->headers->get(CORS::HEADER_ALLOW_HEADERS));

        $response = $this->callGetInWrapperAndGetResponse('get', $wrapper);
        $this->assertNull($response->headers->get(CORS::HEADER_ALLOW_HEADERS));
    }
}
