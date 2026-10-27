<?php
/**
 * Knowledge-base benchmark corpus: a small coffee roaster's site in English and Persian.
 *
 * Each fact document carries one question whose answer is a short, unique "fact" string
 * in the text (or, for products, only in WooCommerce data). Filler posts are generated
 * deterministically so every run sees the same site. Used by tests/kb_benchmark.php.
 */
declare(strict_types=1);

/**
 * @return array{docs: array<int, array<string, mixed>>, questions: array<int, array<string, mixed>>, unanswerable: array<int, array<string, string>>}
 */
function aisaautopilot_kb_benchmark_corpus(): array {
	$guide_sections = array(
		'Espresso'   => 'Espresso is the heart of most café menus. Start with 18 g of finely ground coffee in a double basket and aim for 36 g of liquid espresso in 25 to 30 seconds. If the shot runs fast and tastes sour, grind finer; if it runs slow and tastes bitter, grind coarser. Keep the portafilter warm, distribute the grounds evenly and tamp level. Water between 92 and 94 °C works for most of our roasts. Purge the group head before every shot so the temperature stays stable, and clean the basket with a dry cloth between shots. ',
		'Pour-over'  => 'A pour-over rewards patience. Rinse the paper filter with hot water, add 15 g of medium-fine coffee and pour 250 g of water in slow circles. Let the coffee bloom for 40 seconds after the first 40 g of water, then continue in three pours. The whole brew should take about three minutes. Light roasts like our Ethiopian lots shine in a pour-over because the method keeps the cup clean and bright. Use a gooseneck kettle so you can control the flow. ',
		'AeroPress'  => 'The AeroPress is forgiving and great for travel. Use 14 g of coffee and 200 g of water at 85 °C for a smooth cup, stir ten times, wait one minute and press gently for about 30 seconds. The inverted method gives you a longer steep without dripping. Try a finer grind with a shorter steep when you want something closer to espresso, or a coarser grind with a longer steep for a rounder cup. Rinse the cap and plunger right after brewing. ',
		'Cold brew'  => 'Cold brew is steeped, not brewed. Combine coarsely ground coffee with cold water at a ratio of 1 to 8, stir, cover and leave it in the fridge for 16 to 18 hours. Strain through a paper filter and dilute the concentrate with water or milk to taste. Cold brew keeps for up to ten days in a closed bottle in the fridge. Medium and dark roasts give the chocolate notes most people expect from cold brew. ',
		'Moka pot'   => 'The moka pot makes a strong, espresso-like coffee on the stove. Fill the bottom chamber with hot water up to the valve, add finely ground coffee to the basket without tamping and heat on medium. Take the pot off the heat as soon as the coffee starts to gurgle, and cool the base under the tap so the coffee does not burn. A fine grind similar to table salt works best for our espresso blends. ',
	);
	$guide = '';
	foreach ( $guide_sections as $heading => $text ) {
		$guide .= '<h2>' . $heading . '</h2><p>' . str_repeat( $text, 5 ) . '</p>';
	}
	// The fact sits at the end of a long page: the old summariser only read the first 8,000 characters.
	$guide .= '<h2>French press</h2><p>The French press gives a full-bodied cup with natural oils. For a French press use 60 g of coffee per litre of water, a coarse grind and water just off the boil. Steep for four minutes, break the crust, skim the foam and press slowly.</p>';

	$docs = array(
		array( 'key' => 'hours', 'type' => 'page', 'title' => 'Opening hours', 'lang' => 'en', 'content' => '<p>Our roastery café on Lindenstraße is open Monday to Friday from 7:30 to 18:00 and on Saturday from 9:30 to 14:00. We are closed on Sundays and public holidays.</p><p>The online shop is open around the clock; orders placed before 13:00 on a weekday leave the roastery the same day.</p>' ),
		array( 'key' => 'shipping', 'type' => 'page', 'title' => 'Shipping', 'lang' => 'en', 'content' => '<h2>Delivery in Germany</h2><p>Orders over €40 ship free within Germany. Standard delivery takes 2 to 4 working days with DHL.</p><h2>Express</h2><p>Need it tomorrow? Express next-day delivery costs €7.90 and is available for orders placed before 13:00.</p><h2>Europe</h2><p>We ship to all EU countries for a flat €12.50.</p>' ),
		array( 'key' => 'returns', 'type' => 'page', 'title' => 'Returns and refunds', 'lang' => 'en', 'content' => '<p>Unopened bags of coffee beans can be returned within 14 days of delivery for a full refund. Opened coffee cannot be returned for hygiene reasons, but if a bag tastes wrong, write to us and we will make it right.</p><p>Equipment such as grinders and kettles can be returned within 30 days in its original packaging.</p>' ),
		array( 'key' => 'contact', 'type' => 'page', 'title' => 'Contact us', 'lang' => 'en', 'content' => '<p>Visit our roastery and shop at Lindenstraße 12, 10969 Berlin, five minutes from the U-Bahn station Kochstraße.</p><p>Call us on +49 30 1234 5678 or write to hello@atlas-roasters.example. We answer emails within one working day.</p>' ),
		array( 'key' => 'workshop', 'type' => 'page', 'title' => 'Barista workshop', 'lang' => 'en', 'content' => '<p>Learn to pull a great espresso and steam silky milk. Our three-hour barista workshop costs €89 per person, includes all coffee and milk, and is limited to six participants so everyone gets time on the machine.</p><p>Workshops run every second Thursday at 18:30 in the roastery.</p>' ),
		array( 'key' => 'wholesale', 'type' => 'page', 'title' => 'Wholesale for cafés and offices', 'lang' => 'en', 'content' => '<p>Cafés, restaurants and offices can buy our coffee at wholesale prices from 5 kg per month. Business customers get a 15% discount on the shop price and free delivery in Berlin.</p><p>Apply with the wholesale form and we will send you samples within a week.</p>' ),
		array( 'key' => 'subscription', 'type' => 'page', 'title' => 'Coffee subscription', 'lang' => 'en', 'content' => '<p>Get 250 g of freshly roasted coffee every two or four weeks. You can skip a delivery, change your coffee or cancel at any time from your account.</p><p>Going on holiday? You can pause your subscription for up to 8 weeks without losing your subscriber price.</p>' ),
		array( 'key' => 'guide', 'type' => 'post', 'title' => 'The complete home brewing guide', 'lang' => 'en', 'content' => $guide ),
		array( 'key' => 'faq', 'type' => 'page', 'title' => 'Frequently asked questions', 'lang' => 'en', 'content' => '<h3>How fresh is your coffee?</h3><p>We roast every Tuesday and Friday and ship within two days of roasting.</p><h3>Do you have decaf?</h3><p>Yes. Our decaf is a Colombian coffee decaffeinated with the Swiss Water process, without chemicals.</p><h3>Is your packaging recyclable?</h3><p>Our bags are paper with a compostable lining.</p><h3>Can I pick up my order?</h3><p>Yes, choose pickup at checkout and collect it at the roastery.</p>' ),
		array( 'key' => 'giftcards', 'type' => 'page', 'title' => 'Gift cards', 'lang' => 'en', 'content' => '<p>Our gift cards come in €25, €50 and €100 and can be used in the shop and online. Every gift card is valid for 3 years from the date of purchase.</p>' ),
		array( 'key' => 'yirgacheffe', 'type' => 'product', 'title' => 'Ethiopia Yirgacheffe', 'lang' => 'en', 'content' => '<p>A bright, floral coffee with notes of jasmine, lemon and black tea. Washed process from smallholder farms in Gedeo.</p>', 'price' => '14.50', 'stock' => 'instock', 'attributes' => array( 'Roast' => 'Light', 'Weight' => '250 g' ) ),
		array( 'key' => 'blend7', 'type' => 'product', 'title' => 'Espresso Blend No. 7', 'lang' => 'en', 'content' => '<p>Our house espresso with chocolate, hazelnut and a soft, sweet finish.</p>', 'price' => '12.90', 'stock' => 'outofstock', 'attributes' => array( 'Roast' => 'Medium-dark', 'Weight' => '500 g' ) ),
		array( 'key' => 'grinder', 'type' => 'product', 'title' => 'Comandante C40 hand grinder', 'lang' => 'en', 'content' => '<p>A premium hand grinder with high-nitrogen steel burrs, ideal for pour-over and French press.</p>', 'price' => '249.00', 'stock' => 'instock', 'attributes' => array( 'Material' => 'Stainless steel and wood' ) ),
		array( 'key' => 'staff', 'type' => 'page', 'title' => 'Staff schedule', 'lang' => 'en', 'password' => 'team-only', 'content' => '<p>Internal rota for the roastery team. The staff discount code for the online shop is BEANS50. Do not share it with customers.</p>' ),
		array( 'key' => 'fa_hours', 'type' => 'page', 'title' => 'ساعات کاری شعبه تهران', 'lang' => 'fa', 'content' => '<p>کافه و فروشگاه شعبهٔ تهران شنبه تا چهارشنبه از ساعت ۸ صبح تا ۸ شب باز است. پنجشنبه‌ها تا ساعت ۲ بعدازظهر باز هستیم و جمعه‌ها تعطیل است.</p>' ),
		array( 'key' => 'fa_shipping', 'type' => 'page', 'title' => 'ارسال به سراسر ایران', 'lang' => 'fa', 'content' => '<p>هزینهٔ ارسال پستی سفارش‌ها ۴۵ هزار تومان است و برای سفارش‌های بالای ۸۰۰ هزار تومان رایگان است. تحویل در تهران ۱ تا ۲ روز و در شهرهای دیگر ۳ تا ۵ روز کاری طول می‌کشد.</p>' ),
		array( 'key' => 'fa_workshop', 'type' => 'page', 'title' => 'کارگاه لاته‌آرت', 'lang' => 'fa', 'content' => '<p>در کارگاه لاته‌آرت یاد می‌گیرید شیر را درست بخار بدهید و طرح قلب و برگ بکشید. این کارگاه ۴ ساعته است و هزینهٔ شرکت در آن ۱٫۲ میلیون تومان است. ظرفیت هر دوره ۵ نفر است.</p>' ),
		array( 'key' => 'fa_returns', 'type' => 'page', 'title' => 'شرایط مرجوعی کالا', 'lang' => 'fa', 'content' => '<p>بسته‌های قهوهٔ باز نشده را می‌توانید تا ۷ روز پس از تحویل مرجوع کنید و کل مبلغ را پس بگیرید. قهوهٔ باز شده به دلایل بهداشتی قابل مرجوع کردن نیست.</p>' ),
		array( 'key' => 'fa_contact', 'type' => 'page', 'title' => 'تماس با ما', 'lang' => 'fa', 'content' => '<p>آدرس کافه و فروشگاه: تهران، خیابان ولیعصر، کوچهٔ نهم، پلاک ۲۴. تلفن: ۰۲۱-۸۸۸۸۱۲۳۴. به پیام‌ها در کمتر از یک روز کاری پاسخ می‌دهیم.</p>' ),
		array( 'key' => 'fa_subscription', 'type' => 'page', 'title' => 'اشتراک ماهانهٔ قهوه', 'lang' => 'fa', 'content' => '<p>با اشتراک قهوه هر دو یا چهار هفته ۲۵۰ گرم قهوهٔ تازه‌برشته دریافت می‌کنید. می‌توانید اشتراک را تا ۸ هفته متوقف کنید یا هر زمان از حساب کاربری لغو کنید.</p>' ),
		array( 'key' => 'fa_decaf', 'type' => 'page', 'title' => 'پرسش‌های پرتکرار', 'lang' => 'fa', 'content' => '<h3>قهوهٔ بدون کافئین دارید؟</h3><p>بله، قهوهٔ دیکف ما با روش سوئیس واتر و بدون مواد شیمیایی کافئین‌زدایی شده است.</p><h3>قهوه را آسیاب می‌کنید؟</h3><p>بله، هنگام سفارش درجهٔ آسیاب را انتخاب کنید.</p>' ),
		array( 'key' => 'fa_colombia', 'type' => 'product', 'title' => 'قهوه کلمبیا سوپریمو', 'lang' => 'fa', 'content' => '<p>دانهٔ قهوهٔ کلمبیا با طعم کارامل، سیب سرخ و بدنهٔ متوسط.</p>', 'price' => '14.90', 'stock' => 'instock', 'attributes' => array( 'Roast' => 'Medium' ) ),
	);

	// Questions use the visitor's own wording, not the page's.
	$questions = array(
		array( 'doc' => 'hours', 'lang' => 'en', 'q' => 'What time do you open on Saturday?', 'fact' => '9:30' ),
		array( 'doc' => 'shipping', 'lang' => 'en', 'q' => 'How much is express shipping?', 'fact' => '7.90' ),
		array( 'doc' => 'returns', 'lang' => 'en', 'q' => 'Can I return coffee beans I bought?', 'fact' => '14 days' ),
		array( 'doc' => 'contact', 'lang' => 'en', 'q' => 'Where is your shop located?', 'fact' => 'Lindenstraße 12' ),
		array( 'doc' => 'workshop', 'lang' => 'en', 'q' => 'How much does the barista workshop cost?', 'fact' => '€89' ),
		array( 'doc' => 'wholesale', 'lang' => 'en', 'q' => 'Do you give a discount to offices?', 'fact' => '15%' ),
		array( 'doc' => 'subscription', 'lang' => 'en', 'q' => 'Can I pause my coffee subscription while on holiday?', 'fact' => '8 weeks' ),
		array( 'doc' => 'guide', 'lang' => 'en', 'q' => 'How much coffee should I use for a French press?', 'fact' => '60 g' ),
		array( 'doc' => 'faq', 'lang' => 'en', 'q' => 'Do you sell decaf coffee?', 'fact' => 'Swiss Water' ),
		array( 'doc' => 'giftcards', 'lang' => 'en', 'q' => 'How long is a gift card valid?', 'fact' => '3 years' ),
		array( 'doc' => 'yirgacheffe', 'lang' => 'en', 'q' => 'What is the price of the Ethiopia Yirgacheffe?', 'fact' => '14.50' ),
		array( 'doc' => 'blend7', 'lang' => 'en', 'q' => 'Is the Espresso Blend No. 7 available?', 'fact' => 'out of stock' ),
		array( 'doc' => 'grinder', 'lang' => 'en', 'q' => 'How much is the Comandante grinder?', 'fact' => '249' ),
		array( 'doc' => 'shipping', 'lang' => 'en', 'q' => 'Do you deliver to other EU countries and what does it cost?', 'fact' => '12.50' ),
		array( 'doc' => 'hours', 'lang' => 'en', 'q' => 'If I order before 1 pm, when does it ship?', 'fact' => 'same day' ),
		array( 'doc' => 'fa_hours', 'lang' => 'fa', 'q' => 'پنجشنبه تا چه ساعتی باز هستید؟', 'fact' => 'ساعت ۲ بعدازظهر' ),
		array( 'doc' => 'fa_shipping', 'lang' => 'fa', 'q' => 'هزینه ارسال چقدر است؟', 'fact' => '۴۵ هزار تومان' ),
		array( 'doc' => 'fa_workshop', 'lang' => 'fa', 'q' => 'هزینه کارگاه لاته آرت چقدر است؟', 'fact' => '۱٫۲ میلیون' ),
		array( 'doc' => 'fa_returns', 'lang' => 'fa', 'q' => 'چند روز فرصت دارم قهوه را پس بدهم؟', 'fact' => '۷ روز' ),
		array( 'doc' => 'fa_contact', 'lang' => 'fa', 'q' => 'آدرس کافه کجاست؟', 'fact' => 'پلاک ۲۴' ),
		array( 'doc' => 'fa_subscription', 'lang' => 'fa', 'q' => 'میتونم اشتراکم رو موقتا متوقف کنم؟', 'fact' => '۸ هفته' ),
		array( 'doc' => 'fa_decaf', 'lang' => 'fa', 'q' => 'قهوه بدون کافئین دارید؟', 'fact' => 'سوئیس واتر' ),
		array( 'doc' => 'fa_colombia', 'lang' => 'fa', 'q' => 'قیمت قهوه کلمبیا چنده؟', 'fact' => '14.90' ),
		// Typed on an Arabic keyboard layout: ي and ك instead of ی and ک.
		array( 'doc' => 'fa_colombia', 'lang' => 'fa', 'q' => 'قيمت قهوه كلمبيا چقدر است؟', 'fact' => '14.90' ),
		array( 'doc' => 'fa_shipping', 'lang' => 'fa', 'q' => 'ارسال به شهرستان چند روز طول میکشه؟', 'fact' => '۳ تا ۵ روز' ),
	);

	// Nothing on the site answers these; the right outcome is "not found".
	$unanswerable = array(
		array( 'lang' => 'en', 'q' => 'Do you sell pizza?' ),
		array( 'lang' => 'en', 'q' => 'Can I pay with Bitcoin?' ),
		array( 'lang' => 'en', 'q' => 'Who won the football match yesterday?' ),
		array( 'lang' => 'en', 'q' => 'Do you rent bicycles?' ),
		array( 'lang' => 'fa', 'q' => 'آیا ماشین اجاره می‌دهید؟' ),
		array( 'lang' => 'fa', 'q' => 'قیمت بلیط هواپیما به استانبول چند است؟' ),
		array( 'lang' => 'fa', 'q' => 'کلاس آموزش شنا دارید؟' ),
		array( 'lang' => 'en', 'q' => 'Is there a staff discount code?' ),
	);

	return array(
		'docs'         => $docs,
		'questions'    => $questions,
		'unanswerable' => $unanswerable,
	);
}

/**
 * Deterministic filler blog posts, so the site looks like one that has been blogging for a while.
 *
 * @return array<int, array{key:string, type:string, title:string, lang:string, content:string}>
 */
function aisaautopilot_kb_benchmark_filler( int $count, int $seed ): array {
	mt_srand( $seed );
	$en_topics = array( 'Brazil', 'Kenya', 'Guatemala', 'Sumatra', 'Rwanda', 'Costa Rica', 'Honduras', 'Peru', 'Burundi', 'Yemen', 'Panama', 'Nicaragua' );
	$en_kinds  = array( 'Harvest report from %s', 'Meet the farmers of %s', 'Why we love coffee from %s', 'Roasting notes: %s', 'Our trip to %s', 'New arrival: %s lot' );
	$en_lines  = array(
		'This season the cherries were picked slowly and carefully, and the result is a sweet, balanced cup.',
		'We tasted more than thirty samples before choosing this lot for our roastery.',
		'The farm sits at high altitude, which slows ripening and builds complex sugars in the beans.',
		'Our head roaster developed a profile that keeps the fruit notes clear without losing body.',
		'Expect flavours of stone fruit, brown sugar and a long, clean finish.',
		'The cooperative invests part of every premium in schools and clean water projects.',
		'We pay well above the market price because quality and fairness go together.',
		'Try it as a filter coffee first, then experiment with espresso if you like a bolder cup.',
		'Rain arrived late this year, so the harvest started two weeks after the usual date.',
		'Every bag carries the name of the producer and the date it was roasted.',
		'Customers in our café voted this coffee their favourite of the month.',
		'Store your beans in a cool, dark place and grind just before brewing.',
	);
	$fa_titles = array( 'گزارش برداشت قهوه از %s', 'آشنایی با کشاورزان %s', 'چرا قهوهٔ %s را دوست داریم', 'یادداشت برشته‌کاری: %s', 'سفر ما به %s', 'محصول تازه: قهوهٔ %s' );
	$fa_topics = array( 'برزیل', 'کنیا', 'گواتمالا', 'سوماترا', 'رواندا', 'کاستاریکا', 'هندوراس', 'پرو', 'یمن', 'پاناما' );
	$fa_lines  = array(
		'امسال میوه‌های قهوه با دقت و آرام چیده شدند و نتیجه فنجانی شیرین و متعادل است.',
		'پیش از انتخاب این محصول بیش از سی نمونه را چشیدیم.',
		'مزرعه در ارتفاع بالا قرار دارد و همین باعث می‌شود دانه‌ها قندهای پیچیده‌تری داشته باشند.',
		'برشته‌کار ما پروفایلی ساخت که طعم میوه‌ای را بدون از دست دادن بدنه حفظ کند.',
		'طعم‌هایی مانند هلو، شکر قهوه‌ای و پس‌طعمی بلند و تمیز را انتظار داشته باشید.',
		'تعاونی بخشی از درآمد اضافه را صرف مدرسه و آب سالم می‌کند.',
		'ابتدا آن را به‌صورت دمی امتحان کنید و اگر طعم قوی‌تر می‌خواهید سراغ اسپرسو بروید.',
		'دانه‌ها را در جای خنک و تاریک نگه دارید و درست پیش از دم کردن آسیاب کنید.',
		'روی هر بسته نام تولیدکننده و تاریخ برشته‌کاری نوشته شده است.',
		'مشتریان کافه این قهوه را محبوب‌ترین قهوهٔ ماه انتخاب کردند.',
	);

	$posts = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$fa    = 0 === $i % 3;
		$lines = $fa ? $fa_lines : $en_lines;
		$title = $fa
			? sprintf( $fa_titles[ mt_rand( 0, count( $fa_titles ) - 1 ) ], $fa_topics[ mt_rand( 0, count( $fa_topics ) - 1 ) ] )
			: sprintf( $en_kinds[ mt_rand( 0, count( $en_kinds ) - 1 ) ], $en_topics[ mt_rand( 0, count( $en_topics ) - 1 ) ] );
		$body  = '';
		$paras = mt_rand( 3, 6 );
		for ( $p = 0; $p < $paras; $p++ ) {
			$sentences = array();
			for ( $s = 0, $n = mt_rand( 3, 5 ); $s < $n; $s++ ) {
				$sentences[] = $lines[ mt_rand( 0, count( $lines ) - 1 ) ];
			}
			$body .= '<p>' . implode( ' ', $sentences ) . '</p>';
		}
		$posts[] = array(
			'key'     => 'filler_' . $i,
			'type'    => 'post',
			'title'   => $title . ' #' . ( $i + 1 ),
			'lang'    => $fa ? 'fa' : 'en',
			'content' => $body,
		);
	}

	return $posts;
}
