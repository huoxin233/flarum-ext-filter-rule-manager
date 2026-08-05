<?php

/*
 * This file is part of huoxin/filter-rule-manager.
 *
 * Copyright (c) 2026 huoxin.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Huoxin\FilterRuleManager\Tests\unit;

use Huoxin\FilterRuleManager\Modifier\Builtin\StripImagesModifier;
use Huoxin\FilterRuleManager\Modifier\Builtin\StripMentionsModifier;
use Huoxin\FilterRuleManager\Modifier\Builtin\StripUploadTagsModifier;
use Huoxin\FilterRuleManager\Modifier\Builtin\StripUrlsModifier;
use PHPUnit\Framework\TestCase;

class BuiltinModifiersTest extends TestCase
{
    /** @test */
    public function it_strips_mentions_properly()
    {
        $modifier = new StripMentionsModifier();

        $content = 'Hello @"User Name"#123 and @"Another User"#p456! Also @username here.';
        $expected = 'Hello  and ! Also  here.';

        $this->assertEquals($expected, $modifier->modify($content));
    }

    /** @test */
    public function it_strips_urls_properly()
    {
        $modifier = new StripUrlsModifier();

        $content = 'Check this out http://example.com/test?q=1 and https://google.com for more info.';
        $expected = 'Check this out  and  for more info.';

        $this->assertEquals($expected, $modifier->modify($content));
    }

    /** @test */
    public function it_strips_upload_tags_properly()
    {
        $modifier = new StripUploadTagsModifier();

        $content = 'Here is a file [upl-file uuid=123 size=10MB] and an image [upl-image-preview uuid=456 url=...] test.';
        $expected = 'Here is a file  and an image  test.';

        $this->assertEquals($expected, $modifier->modify($content));
    }

    /** @test */
    public function it_strips_images_properly()
    {
        $modifier = new StripImagesModifier();

        $content = 'Look at this: [img]http://example.com/image.png[/img] and [img width=100]http://example.com/image2.png[/img] and this markdown ![alt text](http://example.com/img2.jpg) image.';
        $expected = 'Look at this:  and  and this markdown  image.';

        $this->assertEquals($expected, $modifier->modify($content));
    }
}
