<?php
/**
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2015 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/

namespace s9e\TextFormatter\Bundles\Forum;

class Renderer extends \s9e\TextFormatter\Renderer
{
	protected $params=['EMOTICONS_PATH'=>'','L_HIDE'=>'Hide','L_SHOW'=>'Show','L_SPOILER'=>'Spoiler','L_WROTE'=>'wrote:'];
	protected static $tagBranches=['B'=>0,'BANDCAMP'=>1,'CENTER'=>2,'CODE'=>3,'COLOR'=>4,'DAILYMOTION'=>5,'E'=>6,'EMAIL'=>7,'FACEBOOK'=>8,'FONT'=>9,'I'=>10,'IMG'=>11,'INDIEGOGO'=>12,'INSTAGRAM'=>13,'KICKSTARTER'=>14,'LI'=>15,'LIST'=>16,'LIVELEAK'=>17,'QUOTE'=>18,'S'=>19,'SIZE'=>20,'SOUNDCLOUD'=>21,'SPOILER'=>22,'TWITCH'=>23,'TWITTER'=>24,'U'=>25,'URL'=>26,'VIMEO'=>27,'VINE'=>28,'WSHH'=>29,'YOUTUBE'=>30,'br'=>31,'e'=>32,'i'=>32,'s'=>32,'p'=>33];
	protected static $bt9C97241C=[':('=>0,':)'=>1,':-('=>2,':-)'=>3,':-*'=>4,':-?'=>5,':-D'=>6,':-P'=>7,':-p'=>8,':-|'=>9,':?'=>10,':D'=>11,':P'=>12,':lol:'=>13,':o'=>14,':p'=>15,':|'=>16,';)'=>17,';-)'=>18];
	public function __sleep()
	{
		$props = get_object_vars($this);
		unset($props['out'], $props['proc'], $props['source']);
		return array_keys($props);
	}
	public function renderRichText($xml)
	{
		if (!isset($this->quickRenderingTest) || !preg_match($this->quickRenderingTest, $xml))
			try
			{
				return $this->renderQuick($xml);
			}
			catch (\Exception $e)
			{
			}
		$dom = $this->loadXML($xml);
		$this->out = '';
		$this->at($dom->documentElement);
		return $this->out;
	}
	protected function at(\DOMNode $root)
	{
		if ($root->nodeType === 3)
			$this->out .= htmlspecialchars($root->textContent,0);
		else
			foreach ($root->childNodes as $node)
				if (!isset(self::$tagBranches[$node->nodeName]))
					$this->at($node);
				else
				{
					$tb = self::$tagBranches[$node->nodeName];
					if($tb<17)if($tb<9)if($tb<5)if($tb<3)if($tb===0){$this->out.='<b>';$this->at($node);$this->out.='</b>';}elseif($tb===1){$this->out.='<div data-s9e-mediaembed="bandcamp" style="display:inline-block;width:100%;max-width:400px"><div style="position:relative;padding-bottom:100%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//bandcamp.com/EmbeddedPlayer/size=large/minimal=true/';if($node->hasAttribute('album_id')){$this->out.='album='.htmlspecialchars($node->getAttribute('album_id'),2);if($node->hasAttribute('track_num'))$this->out.='/t='.htmlspecialchars($node->getAttribute('track_num'),2);}else$this->out.='track='.htmlspecialchars($node->getAttribute('track_id'),2);$this->out.='"></iframe></div></div>';}else{$this->out.='<div style="text-align:center">';$this->at($node);$this->out.='</div>';}elseif($tb===3){$this->out.='<pre data-hljs="" data-s9e-livepreview-postprocess="if(\'undefined\'!==typeof hljs)hljs._hb(this)"><code class="'.htmlspecialchars($node->getAttribute('lang'),2).'">';$this->at($node);$this->out.='</code></pre><script>if("undefined"!==typeof hljs)hljs._ha();else if("undefined"===typeof hljsLoading){hljsLoading=1;var a=document.getElementsByTagName("head")[0],e=document.createElement("link");e.type="text/css";e.rel="stylesheet";e.href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/8.8.0/styles/default.min.css";a.appendChild(e);e=document.createElement("script");e.type="text/javascript";e.onload=function(){var d={},f=0;hljs._hb=function(b){b.removeAttribute("data-hljs");var c=b.innerHTML;c in d?b.innerHTML=d[c]:(7<++f&&(d={},f=0),hljs.highlightBlock(b.firstChild),d[c]=b.innerHTML)};hljs._ha=function(){for(var b=document.querySelectorAll("pre[data-hljs]"),c=b.length;0<c;)hljs._hb(b.item(--c))};hljs._ha()};e.async=!0;e.src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/8.8.0/highlight.min.js";a.appendChild(e)}</script>';}else{$this->out.='<span style="color:'.htmlspecialchars($node->getAttribute('color'),2).'">';$this->at($node);$this->out.='</span>';}elseif($tb===5)$this->out.='<div data-s9e-mediaembed="dailymotion" style="display:inline-block;width:100%;max-width:640px"><div style="position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//www.dailymotion.com/embed/video/'.htmlspecialchars($node->getAttribute('id'),2).'" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>';elseif($tb===6)if(isset(self::$bt9C97241C[$node->textContent])){$n=self::$bt9C97241C[$node->textContent];$this->out.='<img src="'.htmlspecialchars($this->params['EMOTICONS_PATH'],2);if($n<10)if($n<5)if($n<3)if($n===0)$this->out.='/frown.png" alt=":(">';elseif($n===1)$this->out.='/smile.png" alt=":)">';else$this->out.='/frown.png" alt=":-(">';elseif($n===3)$this->out.='/smile.png" alt=":-)">';else$this->out.='/kiss.png" alt=":-*">';elseif($n<8)if($n===5)$this->out.='/confused.png" alt=":-?">';elseif($n===6)$this->out.='/grin.png" alt=":-D">';else$this->out.='/razz.png" alt=":-P">';elseif($n===8)$this->out.='/razz.png" alt=":-p">';else$this->out.='/neutral.png" alt=":-|">';elseif($n<15)if($n<13)if($n===10)$this->out.='/confused.png" alt=":?">';elseif($n===11)$this->out.='/grin.png" alt=":D">';else$this->out.='/razz.png" alt=":P">';elseif($n===13)$this->out.='/laugh.png" alt=":lol:">';else$this->out.='/shock.png" alt=":o">';elseif($n===15)$this->out.='/razz.png" alt=":p">';elseif($n===16)$this->out.='/neutral.png" alt=":|">';elseif($n===17)$this->out.='/wink.png" alt=";)">';else$this->out.='/wink.png" alt=";-)">';}else$this->out.=htmlspecialchars($node->textContent,0);elseif($tb===7){$this->out.='<a href="mailto:'.htmlspecialchars($node->getAttribute('email'),2).'">';$this->at($node);$this->out.='</a>';}else$this->out.='<iframe data-s9e-mediaembed="facebook" allowfullscreen="" onload="var a=Math.random();window.addEventListener(\'message\',function(b){if(b.data.id==a)style.height=b.data.height+\'px\'});contentWindow.postMessage(\'s9e:\'+a,src.substr(0,src.indexOf(\'/\',8)))" scrolling="no" src="//s9e.github.io/iframe/facebook.min.html#'.htmlspecialchars($node->getAttribute('id'),2).'" style="border:0;height:360px;max-width:640px;width:100%"></iframe>';elseif($tb<13)if($tb===9){$this->out.='<span style="font-family:'.htmlspecialchars($node->getAttribute('font'),2).'">';$this->at($node);$this->out.='</span>';}elseif($tb===10){$this->out.='<i>';$this->at($node);$this->out.='</i>';}elseif($tb===11)$this->out.='<img src="'.htmlspecialchars($node->getAttribute('src'),2).'" title="'.htmlspecialchars($node->getAttribute('title'),2).'" alt="'.htmlspecialchars($node->getAttribute('alt'),2).'">';else$this->out.='<div data-s9e-mediaembed="indiegogo" style="display:inline-block;width:100%;max-width:224px"><div style="position:relative;padding-bottom:216.96428571429%"><iframe allowfullscreen="" scrolling="no" src="//www.indiegogo.com/project/'.htmlspecialchars($node->getAttribute('id'),2).'/widget" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>';elseif($tb===13)$this->out.='<div data-s9e-mediaembed="instagram" style="display:inline-block;width:100%;max-width:640px"><div style="position:relative;padding-bottom:112.5%;padding-bottom:calc(100% + 80px)"><iframe allowfullscreen="" scrolling="no" src="//instagram.com/p/'.htmlspecialchars($node->getAttribute('id'),2).'/embed/" style="border:0;box-shadow:rgba(0,0,0,.5) 0 0 1px 0,rgba(0,0,0,.15) 0 1px 10px 0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>';elseif($tb===14)if($node->hasAttribute('video'))$this->out.='<div data-s9e-mediaembed="kickstarter" style="display:inline-block;width:100%;max-width:480px"><div style="position:relative;padding-bottom:75%"><iframe allowfullscreen="" scrolling="no" src="//www.kickstarter.com/projects/'.htmlspecialchars($node->getAttribute('id'),2).'/widget/video.html" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>';else$this->out.='<div data-s9e-mediaembed="kickstarter" style="display:inline-block;width:100%;max-width:220px"><div style="position:relative;padding-bottom:190.90909090909%"><iframe allowfullscreen="" scrolling="no" src="//www.kickstarter.com/projects/'.htmlspecialchars($node->getAttribute('id'),2).'/widget/card.html" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>';elseif($tb===15){$this->out.='<li>';$this->at($node);$this->out.='</li>';}elseif(!$node->hasAttribute('type')){$this->out.='<ul>';$this->at($node);$this->out.='</ul>';}elseif((strpos($node->getAttribute('type'),'decimal')===0)||(strpos($node->getAttribute('type'),'lower')===0)||(strpos($node->getAttribute('type'),'upper')===0)){$this->out.='<ol style="list-style-type:'.htmlspecialchars($node->getAttribute('type'),2).'">';$this->at($node);$this->out.='</ol>';}else{$this->out.='<ul style="list-style-type:'.htmlspecialchars($node->getAttribute('type'),2).'">';$this->at($node);$this->out.='</ul>';}elseif($tb<26)if($tb<22)if($tb<20)if($tb===17)$this->out.='<div data-s9e-mediaembed="liveleak" style="display:inline-block;width:100%;max-width:640px"><div style="position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="http://www.liveleak.com/ll_embed?i='.htmlspecialchars($node->getAttribute('id'),2).'" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>';elseif($tb===18){$this->out.='<blockquote';if(!$node->hasAttribute('author'))$this->out.=' class="uncited"';$this->out.='><div>';if($node->hasAttribute('author'))$this->out.='<cite>'.htmlspecialchars($node->getAttribute('author'),0).' '.htmlspecialchars($this->params['L_WROTE'],0).'</cite>';$this->at($node);$this->out.='</div></blockquote>';}else{$this->out.='<s>';$this->at($node);$this->out.='</s>';}elseif($tb===20){$this->out.='<span style="font-size:'.htmlspecialchars($node->getAttribute('size'),2).'px">';$this->at($node);$this->out.='</span>';}elseif($node->hasAttribute('playlist_id'))$this->out.='<iframe data-s9e-mediaembed="soundcloud" allowfullscreen="" scrolling="no" src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/playlists/'.htmlspecialchars($node->getAttribute('playlist_id'),2).'" style="border:0;height:450px;max-width:900px;width:100%"></iframe>';elseif($node->hasAttribute('track_id'))$this->out.='<iframe data-s9e-mediaembed="soundcloud" allowfullscreen="" scrolling="no" src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/'.htmlspecialchars($node->getAttribute('track_id'),2).'" style="border:0;height:166px;max-width:900px;width:100%"></iframe>';elseif((strpos($node->getAttribute('id'),'://')===false))$this->out.='<iframe data-s9e-mediaembed="soundcloud" allowfullscreen="" scrolling="no" src="https://w.soundcloud.com/player/?url=https%3A//soundcloud.com/'.htmlspecialchars($node->getAttribute('id'),2).'" style="border:0;height:166px;max-width:900px;width:100%"></iframe>';else$this->out.='<iframe data-s9e-mediaembed="soundcloud" allowfullscreen="" scrolling="no" src="https://w.soundcloud.com/player/?url='.htmlspecialchars($node->getAttribute('id'),2).'" style="border:0;height:166px;max-width:900px;width:100%"></iframe>';elseif($tb===22){$this->out.='<div class="spoiler"><div class="spoiler-header"><button onclick="var a=parentNode.nextSibling.style,b=firstChild.style,c=lastChild.style;\'\'!==a.display?(a.display=c.display=\'\',b.display=\'none\'):(a.display=c.display=\'none\',b.display=\'\')"><span>'.htmlspecialchars($this->params['L_SHOW'],0).'</span><span style="display:none">'.htmlspecialchars($this->params['L_HIDE'],0).'</span></button><span class="spoiler-title">'.htmlspecialchars($this->params['L_SPOILER'],0).' '.htmlspecialchars($node->getAttribute('title'),0).'</span></div><div class="spoiler-content" style="display:none">';$this->at($node);$this->out.='</div></div>';}elseif($tb===23){$this->out.='<div data-s9e-mediaembed="twitch" style="display:inline-block;width:100%;max-width:640px"><div style="position:relative;padding-bottom:60.9375%;padding-bottom:calc(56.25% + 30px)"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//s9e.github.io/iframe/twitch.min.html#channel='.htmlspecialchars($node->getAttribute('channel'),2);if($node->hasAttribute('archive_id'))$this->out.='&amp;videoId=a'.htmlspecialchars($node->getAttribute('archive_id'),2);elseif($node->hasAttribute('chapter_id'))$this->out.='&amp;videoId=c'.htmlspecialchars($node->getAttribute('chapter_id'),2);elseif($node->hasAttribute('video_id'))$this->out.='&amp;videoId=v'.htmlspecialchars($node->getAttribute('video_id'),2);$this->out.='"></iframe></div></div>';}elseif($tb===24)$this->out.='<iframe data-s9e-mediaembed="twitter" allowfullscreen="" onload="var a=Math.random();window.addEventListener(\'message\',function(b){if(b.data.id==a)style.height=b.data.height+\'px\'});contentWindow.postMessage(\'s9e:\'+a,src.substr(0,src.indexOf(\'/\',8)))" scrolling="no" src="//s9e.github.io/iframe/twitter.min.html#'.htmlspecialchars($node->getAttribute('id'),2).'" style="border:0;height:186px;max-width:500px;width:100%"></iframe>';else{$this->out.='<u>';$this->at($node);$this->out.='</u>';}elseif($tb<30)if($tb===26){$this->out.='<a href="'.htmlspecialchars($node->getAttribute('url'),2).'"';if($node->hasAttribute('title'))$this->out.=' title="'.htmlspecialchars($node->getAttribute('title'),2).'"';$this->out.='>';$this->at($node);$this->out.='</a>';}elseif($tb===27)$this->out.='<div data-s9e-mediaembed="vimeo" style="display:inline-block;width:100%;max-width:640px"><div style="position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//player.vimeo.com/video/'.htmlspecialchars($node->getAttribute('id'),2).'" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>';elseif($tb===28)$this->out.='<div data-s9e-mediaembed="vine" style="display:inline-block;width:100%;max-width:480px"><div style="position:relative;padding-bottom:100%"><iframe allowfullscreen="" scrolling="no" src="https://vine.co/v/'.htmlspecialchars($node->getAttribute('id'),2).'/embed/simple?audio=1" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>';else$this->out.='<div data-s9e-mediaembed="wshh" style="display:inline-block;width:100%;max-width:640px"><div style="position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//www.worldstarhiphop.com/embed/'.htmlspecialchars($node->getAttribute('id'),2).'" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>';elseif($tb===30){$this->out.='<div data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><div style="position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//www.youtube.com/embed/'.htmlspecialchars($node->getAttribute('id'),2);if($node->hasAttribute('list'))$this->out.='?list='.htmlspecialchars($node->getAttribute('list'),2);if($node->hasAttribute('t')||$node->hasAttribute('m')){if($node->hasAttribute('list'))$this->out.='&amp;';else$this->out.='?';$this->out.='start=';if($node->hasAttribute('t'))$this->out.=htmlspecialchars($node->getAttribute('t'),2);elseif($node->hasAttribute('h'))$this->out.=htmlspecialchars($node->getAttribute('h')*3600+$node->getAttribute('m')*60+$node->getAttribute('s'),2);else$this->out.=htmlspecialchars($node->getAttribute('m')*60+$node->getAttribute('s'),2);}$this->out.='"></iframe></div></div>';}elseif($tb===31)$this->out.='<br>';elseif($tb===32);else{$this->out.='<p>';$this->at($node);$this->out.='</p>';}
				}
	}
	private static $static=['/B'=>'</b>','/CENTER'=>'</div>','/CODE'=>'</code></pre><script>if("undefined"!==typeof hljs)hljs._ha();else if("undefined"===typeof hljsLoading){hljsLoading=1;var a=document.getElementsByTagName("head")[0],e=document.createElement("link");e.type="text/css";e.rel="stylesheet";e.href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/8.8.0/styles/default.min.css";a.appendChild(e);e=document.createElement("script");e.type="text/javascript";e.onload=function(){var d={},f=0;hljs._hb=function(b){b.removeAttribute("data-hljs");var c=b.innerHTML;c in d?b.innerHTML=d[c]:(7<++f&&(d={},f=0),hljs.highlightBlock(b.firstChild),d[c]=b.innerHTML)};hljs._ha=function(){for(var b=document.querySelectorAll("pre[data-hljs]"),c=b.length;0<c;)hljs._hb(b.item(--c))};hljs._ha()};e.async=!0;e.src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/8.8.0/highlight.min.js";a.appendChild(e)}</script>','/COLOR'=>'</span>','/EMAIL'=>'</a>','/FONT'=>'</span>','/I'=>'</i>','/LI'=>'</li>','/QUOTE'=>'</div></blockquote>','/S'=>'</s>','/SIZE'=>'</span>','/SPOILER'=>'</div></div>','/U'=>'</u>','/URL'=>'</a>','B'=>'<b>','CENTER'=>'<div style="text-align:center">','I'=>'<i>','LI'=>'<li>','S'=>'<s>','U'=>'<u>'];
	private static $dynamic=['CODE'=>['(^[^ ]+(?> (?!lang=)[^=]+="[^"]*")*(?> lang="([^"]*)")?.*)s','<pre data-hljs="" data-s9e-livepreview-postprocess="if(\'undefined\'!==typeof hljs)hljs._hb(this)"><code class="$1">'],'COLOR'=>['(^[^ ]+(?> (?!color=)[^=]+="[^"]*")*(?> color="([^"]*)")?.*)s','<span style="color:$1">'],'DAILYMOTION'=>['(^[^ ]+(?> (?!id=)[^=]+="[^"]*")*(?> id="([^"]*)")?.*)s','<div data-s9e-mediaembed="dailymotion" style="display:inline-block;width:100%;max-width:640px"><div style="position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//www.dailymotion.com/embed/video/$1" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>'],'EMAIL'=>['(^[^ ]+(?> (?!email=)[^=]+="[^"]*")*(?> email="([^"]*)")?.*)s','<a href="mailto:$1">'],'FACEBOOK'=>['(^[^ ]+(?> (?!id=)[^=]+="[^"]*")*(?> id="([^"]*)")?.*)s','<iframe data-s9e-mediaembed="facebook" allowfullscreen="" onload="var a=Math.random();window.addEventListener(\'message\',function(b){if(b.data.id==a)style.height=b.data.height+\'px\'});contentWindow.postMessage(\'s9e:\'+a,src.substr(0,src.indexOf(\'/\',8)))" scrolling="no" src="//s9e.github.io/iframe/facebook.min.html#$1" style="border:0;height:360px;max-width:640px;width:100%"></iframe>'],'FONT'=>['(^[^ ]+(?> (?!font=)[^=]+="[^"]*")*(?> font="([^"]*)")?.*)s','<span style="font-family:$1">'],'IMG'=>['(^[^ ]+(?> (?!(?>alt|src|title)=)[^=]+="[^"]*")*(?> alt="([^"]*)")?(?> (?!(?>src|title)=)[^=]+="[^"]*")*(?> src="([^"]*)")?(?> (?!title=)[^=]+="[^"]*")*(?> title="([^"]*)")?.*)s','<img src="$2" title="$3" alt="$1">'],'INDIEGOGO'=>['(^[^ ]+(?> (?!id=)[^=]+="[^"]*")*(?> id="([^"]*)")?.*)s','<div data-s9e-mediaembed="indiegogo" style="display:inline-block;width:100%;max-width:224px"><div style="position:relative;padding-bottom:216.96428571429%"><iframe allowfullscreen="" scrolling="no" src="//www.indiegogo.com/project/$1/widget" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>'],'INSTAGRAM'=>['(^[^ ]+(?> (?!id=)[^=]+="[^"]*")*(?> id="([^"]*)")?.*)s','<div data-s9e-mediaembed="instagram" style="display:inline-block;width:100%;max-width:640px"><div style="position:relative;padding-bottom:112.5%;padding-bottom:calc(100% + 80px)"><iframe allowfullscreen="" scrolling="no" src="//instagram.com/p/$1/embed/" style="border:0;box-shadow:rgba(0,0,0,.5) 0 0 1px 0,rgba(0,0,0,.15) 0 1px 10px 0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>'],'LIVELEAK'=>['(^[^ ]+(?> (?!id=)[^=]+="[^"]*")*(?> id="([^"]*)")?.*)s','<div data-s9e-mediaembed="liveleak" style="display:inline-block;width:100%;max-width:640px"><div style="position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="http://www.liveleak.com/ll_embed?i=$1" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>'],'SIZE'=>['(^[^ ]+(?> (?!size=)[^=]+="[^"]*")*(?> size="([^"]*)")?.*)s','<span style="font-size:$1px">'],'TWITTER'=>['(^[^ ]+(?> (?!id=)[^=]+="[^"]*")*(?> id="([^"]*)")?.*)s','<iframe data-s9e-mediaembed="twitter" allowfullscreen="" onload="var a=Math.random();window.addEventListener(\'message\',function(b){if(b.data.id==a)style.height=b.data.height+\'px\'});contentWindow.postMessage(\'s9e:\'+a,src.substr(0,src.indexOf(\'/\',8)))" scrolling="no" src="//s9e.github.io/iframe/twitter.min.html#$1" style="border:0;height:186px;max-width:500px;width:100%"></iframe>'],'URL'=>['(^[^ ]+(?> (?!(?>title|url)=)[^=]+="[^"]*")*( title="[^"]*")?(?> (?!url=)[^=]+="[^"]*")*(?> url="([^"]*)")?.*)s','<a href="$2"$1>'],'VIMEO'=>['(^[^ ]+(?> (?!id=)[^=]+="[^"]*")*(?> id="([^"]*)")?.*)s','<div data-s9e-mediaembed="vimeo" style="display:inline-block;width:100%;max-width:640px"><div style="position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//player.vimeo.com/video/$1" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>'],'VINE'=>['(^[^ ]+(?> (?!id=)[^=]+="[^"]*")*(?> id="([^"]*)")?.*)s','<div data-s9e-mediaembed="vine" style="display:inline-block;width:100%;max-width:480px"><div style="position:relative;padding-bottom:100%"><iframe allowfullscreen="" scrolling="no" src="https://vine.co/v/$1/embed/simple?audio=1" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>'],'WSHH'=>['(^[^ ]+(?> (?!id=)[^=]+="[^"]*")*(?> id="([^"]*)")?.*)s','<div data-s9e-mediaembed="wshh" style="display:inline-block;width:100%;max-width:640px"><div style="position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" src="//www.worldstarhiphop.com/embed/$1" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>']];
	private static $attributes;
	private static $quickBranches=['/LIST'=>0,'BANDCAMP'=>1,'E'=>2,'KICKSTARTER'=>3,'LIST'=>4,'QUOTE'=>5,'SOUNDCLOUD'=>6,'SPOILER'=>7,'TWITCH'=>8,'YOUTUBE'=>9];

	protected function renderQuick($xml)
	{
		$xml = $this->decodeSMP($xml);
		self::$attributes = [];
		$html = preg_replace_callback(
			'(<(?:(?!/)((?>E|BANDCAMP|DAILYMOTION|FACEBOOK|I(?>MG|N(?>DIEGOGO|STAGRAM))|KICKSTARTER|LIVELEAK|SOUNDCLOUD|TWIT(?>CH|TER)|VI(?>MEO|NE)|WSHH|YOUTUBE))(?: [^>]*)?>.*?</\\1|(/?(?!br/|p>)[^ />]+)[^>]*?(/)?)>)',
			[$this, 'quick'],
			preg_replace(
				'(<[eis]>[^<]*</[eis]>)',
				'',
				substr($xml, 1 + strpos($xml, '>'), -4)
			)
		);

		return str_replace('<br/>', '<br>', $html);
	}

	protected function quick($m)
	{
		if (isset($m[2]))
		{
			$id = $m[2];

			if (isset($m[3]))
			{
				unset($m[3]);

				$m[0] = substr($m[0], 0, -2) . '>';
				$html = $this->quick($m);

				$m[0] = '</' . $id . '>';
				$m[2] = '/' . $id;
				$html .= $this->quick($m);

				return $html;
			}
		}
		else
		{
			$id = $m[1];

			$lpos = 1 + strpos($m[0], '>');
			$rpos = strrpos($m[0], '<');
			$textContent = substr($m[0], $lpos, $rpos - $lpos);

			if (strpos($textContent, '<') !== false)
				throw new \RuntimeException;

			$textContent = htmlspecialchars_decode($textContent);
		}

		if (isset(self::$static[$id]))
			return self::$static[$id];

		if (isset(self::$dynamic[$id]))
		{
			list($match, $replace) = self::$dynamic[$id];
			return preg_replace($match, $replace, $m[0], 1, $cnt);
		}

		if (!isset(self::$quickBranches[$id]))
		{
			if ($id[0] === '!' || $id[0] === '?')
				throw new \RuntimeException;
			return '';
		}

		$attributes = [];
		if (strpos($m[0], '="') !== false)
		{
			preg_match_all('(([^ =]++)="([^"]*))S', substr($m[0], 0, strpos($m[0], '>')), $matches);
			foreach ($matches[1] as $i => $attrName)
				$attributes[$attrName] = $matches[2][$i];
		}

		$qb = self::$quickBranches[$id];
		if($qb<5)if($qb<3)if($qb===0){$attributes=array_pop(self::$attributes);$html='';if(!isset($attributes['type']))$html.='</ul>';elseif((strpos($attributes['type'],'decimal')===0)||(strpos($attributes['type'],'lower')===0)||(strpos($attributes['type'],'upper')===0))$html.='</ol>';else$html.='</ul>';}elseif($qb===1){$attributes+=['track_num'=>null,'track_id'=>null];$html='<div data-s9e-mediaembed="bandcamp" style="display:inline-block;width:100%;max-width:400px"><div style="position:relative;padding-bottom:100%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//bandcamp.com/EmbeddedPlayer/size=large/minimal=true/';if(isset($attributes['album_id'])){$html.='album='.$attributes['album_id'];if(isset($attributes['track_num']))$html.='/t='.$attributes['track_num'];}else$html.='track='.$attributes['track_id'];$html.='"></iframe></div></div>';}else{$html='';if(isset(self::$bt9C97241C[$textContent])){$n=self::$bt9C97241C[$textContent];$html.='<img src="'.htmlspecialchars($this->params['EMOTICONS_PATH'],2);if($n<10)if($n<5)if($n<3)if($n===0)$html.='/frown.png" alt=":(">';elseif($n===1)$html.='/smile.png" alt=":)">';else$html.='/frown.png" alt=":-(">';elseif($n===3)$html.='/smile.png" alt=":-)">';else$html.='/kiss.png" alt=":-*">';elseif($n<8)if($n===5)$html.='/confused.png" alt=":-?">';elseif($n===6)$html.='/grin.png" alt=":-D">';else$html.='/razz.png" alt=":-P">';elseif($n===8)$html.='/razz.png" alt=":-p">';else$html.='/neutral.png" alt=":-|">';elseif($n<15)if($n<13)if($n===10)$html.='/confused.png" alt=":?">';elseif($n===11)$html.='/grin.png" alt=":D">';else$html.='/razz.png" alt=":P">';elseif($n===13)$html.='/laugh.png" alt=":lol:">';else$html.='/shock.png" alt=":o">';elseif($n===15)$html.='/razz.png" alt=":p">';elseif($n===16)$html.='/neutral.png" alt=":|">';elseif($n===17)$html.='/wink.png" alt=";)">';else$html.='/wink.png" alt=";-)">';}else$html.=htmlspecialchars($textContent,0);}elseif($qb===3){$attributes+=['id'=>null];$html='';if(isset($attributes['video']))$html.='<div data-s9e-mediaembed="kickstarter" style="display:inline-block;width:100%;max-width:480px"><div style="position:relative;padding-bottom:75%"><iframe allowfullscreen="" scrolling="no" src="//www.kickstarter.com/projects/'.$attributes['id'].'/widget/video.html" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>';else$html.='<div data-s9e-mediaembed="kickstarter" style="display:inline-block;width:100%;max-width:220px"><div style="position:relative;padding-bottom:190.90909090909%"><iframe allowfullscreen="" scrolling="no" src="//www.kickstarter.com/projects/'.$attributes['id'].'/widget/card.html" style="border:0;height:100%;left:0;position:absolute;width:100%"></iframe></div></div>';}else{$attributes+=['type'=>null];$html='';if(!isset($attributes['type']))$html.='<ul>';elseif((strpos($attributes['type'],'decimal')===0)||(strpos($attributes['type'],'lower')===0)||(strpos($attributes['type'],'upper')===0))$html.='<ol style="list-style-type:'.$attributes['type'].'">';else$html.='<ul style="list-style-type:'.$attributes['type'].'">';self::$attributes[]=$attributes;}elseif($qb<8)if($qb===5){$html='<blockquote';if(!isset($attributes['author']))$html.=' class="uncited"';$html.='><div>';if(isset($attributes['author']))$html.='<cite>'.str_replace('&quot;','"',$attributes['author']).' '.htmlspecialchars($this->params['L_WROTE'],0).'</cite>';}elseif($qb===6){$attributes+=['id'=>null];$html='';if(isset($attributes['playlist_id']))$html.='<iframe data-s9e-mediaembed="soundcloud" allowfullscreen="" scrolling="no" src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/playlists/'.$attributes['playlist_id'].'" style="border:0;height:450px;max-width:900px;width:100%"></iframe>';elseif(isset($attributes['track_id']))$html.='<iframe data-s9e-mediaembed="soundcloud" allowfullscreen="" scrolling="no" src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/'.$attributes['track_id'].'" style="border:0;height:166px;max-width:900px;width:100%"></iframe>';elseif((strpos($attributes['id'],'://')===false))$html.='<iframe data-s9e-mediaembed="soundcloud" allowfullscreen="" scrolling="no" src="https://w.soundcloud.com/player/?url=https%3A//soundcloud.com/'.$attributes['id'].'" style="border:0;height:166px;max-width:900px;width:100%"></iframe>';else$html.='<iframe data-s9e-mediaembed="soundcloud" allowfullscreen="" scrolling="no" src="https://w.soundcloud.com/player/?url='.$attributes['id'].'" style="border:0;height:166px;max-width:900px;width:100%"></iframe>';}else{$attributes+=['title'=>null];$html='<div class="spoiler"><div class="spoiler-header"><button onclick="var a=parentNode.nextSibling.style,b=firstChild.style,c=lastChild.style;\'\'!==a.display?(a.display=c.display=\'\',b.display=\'none\'):(a.display=c.display=\'none\',b.display=\'\')"><span>'.htmlspecialchars($this->params['L_SHOW'],0).'</span><span style="display:none">'.htmlspecialchars($this->params['L_HIDE'],0).'</span></button><span class="spoiler-title">'.htmlspecialchars($this->params['L_SPOILER'],0).' '.str_replace('&quot;','"',$attributes['title']).'</span></div><div class="spoiler-content" style="display:none">';}elseif($qb===8){$attributes+=['channel'=>null];$html='<div data-s9e-mediaembed="twitch" style="display:inline-block;width:100%;max-width:640px"><div style="position:relative;padding-bottom:60.9375%;padding-bottom:calc(56.25% + 30px)"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//s9e.github.io/iframe/twitch.min.html#channel='.$attributes['channel'];if(isset($attributes['archive_id']))$html.='&amp;videoId=a'.$attributes['archive_id'];elseif(isset($attributes['chapter_id']))$html.='&amp;videoId=c'.$attributes['chapter_id'];elseif(isset($attributes['video_id']))$html.='&amp;videoId=v'.$attributes['video_id'];$html.='"></iframe></div></div>';}else{$attributes+=['id'=>null,'m'=>null,'s'=>null];$html='<div data-s9e-mediaembed="youtube" style="display:inline-block;width:100%;max-width:640px"><div style="position:relative;padding-bottom:56.25%"><iframe allowfullscreen="" scrolling="no" style="border:0;height:100%;left:0;position:absolute;width:100%" src="//www.youtube.com/embed/'.$attributes['id'];if(isset($attributes['list']))$html.='?list='.$attributes['list'];if(isset($attributes['t'])||isset($attributes['m'])){if(isset($attributes['list']))$html.='&amp;';else$html.='?';$html.='start=';if(isset($attributes['t']))$html.=$attributes['t'];elseif(isset($attributes['h']))$html.=htmlspecialchars($attributes['h']*3600+$attributes['m']*60+$attributes['s'],2);else$html.=htmlspecialchars($attributes['m']*60+$attributes['s'],2);}$html.='"></iframe></div></div>';}

		return $html;
	}
}