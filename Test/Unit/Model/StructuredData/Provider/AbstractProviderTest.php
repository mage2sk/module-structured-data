<?php
declare(strict_types=1);

namespace Panth\StructuredData\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\StructuredData\Model\StructuredData\Provider\AbstractProvider;

class AbstractProviderTest extends AbstractProviderTestCase
{
    private function subject(
        array $registry = [],
        ?RequestInterface $request = null,
        ?StoreManagerInterface $storeManager = null
    ): AbstractProvider {
        return new class (
            $this->registry($registry),
            $request ?? $this->request(),
            $storeManager ?? $this->storeManager(),
            $this->config()
        ) extends AbstractProvider {
            public function getCode(): string
            {
                return 'probe';
            }

            public function getJsonLd(): array
            {
                return [];
            }

            public function call(string $method, mixed ...$args): mixed
            {
                return $this->{$method}(...$args);
            }
        };
    }

    public function testDefaultsAndContextLookups(): void
    {
        $subject = $this->subject(['current_product' => new \stdClass(), 'current_category' => 'x']);

        $this->assertTrue($subject->isApplicable());
        $this->assertNull($subject->call('getCurrentProduct'));
        $this->assertNull($subject->call('getCurrentCategory'));
        $this->assertNull($subject->call('getCurrentCmsPage'));
        $this->assertSame('', $subject->call('getFullActionName'));
        $this->assertFalse($subject->call('isCmsHomePage'));
    }

    public function testFullActionNameIsLowercasedAndFailuresAreSwallowed(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getFullActionName')->willReturn('CMS_Index_Index');
        $broken = $this->createStub(Http::class);
        $broken->method('getFullActionName')->willThrowException(new \RuntimeException('router'));

        $this->assertSame('cms_index_index', $this->subject([], $request)->call('getFullActionName'));
        $this->assertTrue($this->subject([], $request)->call('isCmsHomePage'));
        $this->assertSame('', $this->subject([], $broken)->call('getFullActionName'));
    }

    public function testBaseUrlAndSoftwareCheckSurviveStoreFailure(): void
    {
        $subject = $this->subject([], null, $this->failingStoreManager());

        $this->assertSame('/', $subject->call('getBaseUrl'));
        $this->assertFalse($subject->call('isSoftwareProduct', $this->product()));
        $this->assertSame('https://example.com/', $this->subject()->call('getBaseUrl'));
    }

    public function testSafeAttributeText(): void
    {
        $subject = $this->subject();
        $throwing = $this->product(['getAttributeText' => static fn() => throw new \RuntimeException('eav')]);

        $this->assertSame('', $subject->call('safeAttributeText', $this->createStub(ProductInterface::class), 'color'));
        $this->assertSame('', $subject->call('safeAttributeText', $throwing, 'color'));
        $this->assertSame('Red', $subject->call('safeAttributeText', $this->product(['getAttributeText' => 'Red']), 'color'));
    }

    public function testConvertPrice(): void
    {
        $subject = $this->subject();
        $rate = $this->createStub(PriceCurrencyInterface::class);
        $rate->method('convert')->willReturn('12.5');

        $this->assertSame(10.0, $subject->call('convertPrice', 10.0, null));
        $this->assertSame(12.5, $subject->call('convertPrice', 10.0, $rate));
    }

    public function testResolveOfferUrl(): void
    {
        $subject = $this->subject();
        $parent = 'https://example.com/parent.html';

        $this->assertSame($parent, $subject->call('resolveOfferUrl', $this->product(['getVisibility' => 0]), $parent));
        $this->assertSame($parent, $subject->call('resolveOfferUrl', $this->product(['getVisibility' => 1]), $parent));
        $this->assertSame(
            $parent,
            $subject->call('resolveOfferUrl', $this->product(['getVisibility' => 4, 'getProductUrl' => '']), $parent)
        );
        $this->assertSame(
            'https://example.com/child.html',
            $subject->call(
                'resolveOfferUrl',
                $this->product(['getVisibility' => 2, 'getProductUrl' => 'https://example.com/child.html']),
                $parent
            )
        );
    }

    public function testNormalizeEmail(): void
    {
        $subject = $this->subject();

        $this->assertSame('a@b.test', $subject->call('normalizeEmail', '  MAILTO:a@b.test '));
        $this->assertSame('a@b.test', $subject->call('normalizeEmail', 'a@b.test'));
        $this->assertSame('', $subject->call('normalizeEmail', '   '));
    }

    public function testPlainTextStripsTagsAndDecodesEntities(): void
    {
        $subject = $this->subject();

        $this->assertSame('Solid & sturdy.', $subject->call('plainText', '  Solid <b>&amp; sturdy</b>.  '));
        $this->assertSame(
            "Line one\n" . "\u{2022} Two \"quoted\" it's",
            $subject->call('plainText', "<p>Line   one</p>\n&bull; Two&nbsp;&quot;quoted&quot; it&#039;s")
        );
        $this->assertSame('', $subject->call('plainText', '<br/>'));
    }
}
