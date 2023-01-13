-- MySQL dump 10.13  Distrib 8.0.29, for Linux (x86_64)
--
-- Host: localhost    Database: fluid_dev
-- ------------------------------------------------------
-- Server version	8.0.31-0ubuntu0.20.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `active_ingredients`
--

DROP TABLE IF EXISTS `active_ingredients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `active_ingredients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `active_ingredients`
--

LOCK TABLES `active_ingredients` WRITE;
/*!40000 ALTER TABLE `active_ingredients` DISABLE KEYS */;
INSERT INTO `active_ingredients` VALUES (16,'Enroquin','2022-10-26 07:22:34','2022-10-26 09:22:34');
/*!40000 ALTER TABLE `active_ingredients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `addresses`
--

DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addresses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinic_id` int DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) DEFAULT NULL,
  `is_default_billing` tinyint(1) DEFAULT NULL,
  `type` int NOT NULL,
  `clinic_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intl_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suite` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT NULL,
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_6FCA7516CC22AD4` (`clinic_id`),
  CONSTRAINT `FK_6FCA7516CC22AD4` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `addresses`
--

LOCK TABLES `addresses` WRITE;
/*!40000 ALTER TABLE `addresses` DISABLE KEYS */;
/*!40000 ALTER TABLE `addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `api`
--

DROP TABLE IF EXISTS `api`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api`
--

LOCK TABLES `api` WRITE;
/*!40000 ALTER TABLE `api` DISABLE KEYS */;
/*!40000 ALTER TABLE `api` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `api_details`
--

DROP TABLE IF EXISTS `api_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `api_id` int NOT NULL,
  `distributor_id` int NOT NULL,
  `client_id` varchar(255) NOT NULL,
  `client_secret` varchar(255) NOT NULL,
  `organization_id` varchar(255) NOT NULL,
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_api_1_idx` (`distributor_id`),
  CONSTRAINT `api_details_ibfk_1` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_details`
--

LOCK TABLES `api_details` WRITE;
/*!40000 ALTER TABLE `api_details` DISABLE KEYS */;
/*!40000 ALTER TABLE `api_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `article_details`
--

DROP TABLE IF EXISTS `article_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `article_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `article_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `copy` text NOT NULL,
  `user_id` int NOT NULL DEFAULT '0',
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_support_articles_1_idx` (`article_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `article_details_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `article_details_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article_details`
--

LOCK TABLES `article_details` WRITE;
/*!40000 ALTER TABLE `article_details` DISABLE KEYS */;
INSERT INTO `article_details` VALUES (1,1,'Tips on Remembering to Use Fluid','Here are some tips & tricks to help you remember to use Fluid','<p class=\no-margin>Sometimes, practices forget to consistently use Fluid and they fall back into their old ways of ordering by going directly to the vendor\'s website or simply giving their rep a call to place orders. If this sounds like your practice - don\'t worry - it happens!</p>\r\n<p class=\no-margin>If you\'ve found that you or your staff periodically forget to utilize Fluid, this article provides tips, tricks, and pointers to make sure that it doesn\'t fall by the wayside.</p>\r\n<h4 id=h_6da9970923 data-post-processed=	rue>Bookmark the Fluid website</h4>\r\n<p class=\no-margin>It\'s helpful to save Fluid as a bookmark so that you (or anyone on your staff) can easily locate the website.</p>\r\n<h4 id=h_98bc59a76e data-post-processed=	rue>How to bookmark in Chrome</h4>\r\n<ol>\r\n<li>\r\n<p class=\no-margin>Open Google Chrome on your Mac or PC and navigate to <a class=intercom-content-link href=https://shop.Fluid.com/react/ target=\\_blank rel=\nofollow noopener noreferrer>shop.Fluid.com</a>.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Click the star on the right edge of the address bar. A bookmark will be automatically created</p>\r\n</li>\r\n</ol>\r\n<h4 id=h_6da6347f6c data-post-processed=	rue>How to bookmark in Safari</h4>\r\n<ol>\r\n<li>\r\n<p class=\no-margin>In the Safari app on your Mac, go to <a class=intercom-content-link href=https://shop.Fluid.com/react/ target=\\_blank rel=\nofollow noopener noreferrer>shop.Fluid.com</a></p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Click the Share button in the toolbar, then choose Add Bookmark.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Choose where to add the bookmark, and rename it if you like. Click Add.</p>\r\n</li>\r\n</ol>\r\n<p>How to bookmark in Internet Explorer</p>\r\n<ol>\r\n<li>\r\n<p class=\no-margin>Open Internet Explorer browser</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Go to <a class=intercom-content-link href=https://shop.Fluid.com/react/ target=\\_blank rel=\nofollow noopener noreferrer>shop.Fluid.com</a></p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Right-click on webpage</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Select &ldquo;Add to Favorites&rdquo; from the drop-down menu that will appear</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>In the &ldquo;Add a Favorite window</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Name your bookmark</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Save where you want the bookmark to live in the &ldquo;Create In&rdquo; field</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Click &ldquo;Add&rdquo; to bookmark the webpage</p>\r\n</li>\r\n</ol>\r\n<h4 id=h_6024c360df data-post-processed=	rue>How to bookmark in Microsoft Edge</h4>\r\n<ol>\r\n<li>\r\n<p class=\no-margin>Open Microsoft Edge browser</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Go to the webpage you want to bookmark</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>At the end of the address bar at the top of the browser window, click the star icon</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Name the bookmark</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Select the folder you want it saved in</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Click &ldquo;Add&rdquo; to bookmark the webpage</p>\r\n</li>\r\n</ol>\r\n<h4 id=h_490b5a139f data-post-processed=	rue>How to bookmark in Firefox</h4>\r\n<ol>\r\n<li>\r\n<p class=\no-margin>Open Firefox</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Go to <a class=intercom-content-link href=https://shop.Fluid.com/react/ target=\\_blank rel=\nofollow noopener noreferrer>shop.Fluid.com</a></p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Select the star on the address bar</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>From the menu that drops down, give your bookmark a name, then select Done</p>\r\n</li>\r\n</ol>\r\n<h4 id=h_29f49f3eab data-post-processed=	rue>Save a desktop shortcut</h4>\r\n<p class=\no-margin>Out of sight, out of mind, right? We also recommend saving a shortcut to the Fluid website directly onto your desktop, taskbar, and start menu! This will create an icon that you can click on to go directly to Fluid. This way, it\'s always visible and easily accessible.</p>\r\n<h4 id=h_1d2e105e87 data-post-processed=	rue>How to create a desktop shortcut to Fluid on an Apple device</h4>\r\n<ol>\r\n<li>\r\n<p class=\no-margin>Open the Fluid website in your web browser.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>You want to resize your browser so you can see your desktop.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Then go to the website you want to create a desktop shortcut for.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Next, select the URL in the address bar.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Then drag the URL to your desktop to create the shortcut.</p>\r\n</li>\r\n</ol>\r\n<h4 id=h_7b1010b2bb data-post-processed=	rue>How to create a desktop shortcut to Fluid on a PC device</h4>\r\n<ol>\r\n<li>\r\n<p class=\no-margin>Open your desired website.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Click the Three-Dot menu icon in the top right corner of your screen.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Click More Tools.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Click Pin to Taskbar.</p>\r\n</li>\r\n</ol>\r\n<h4 id=h_84b56ca940 data-post-processed=	rue>Keep your contact information updated</h4>\r\n<p class=\no-margin>Make sure your contact information is up to date on your <a class=intercom-content-link href=https://shop.Fluid.com/react/account/profile target=\\_blank rel=\nofollow noopener noreferrer>Fluid Account &amp; Settings page</a>. The first and last name should be the name of the person who actively uses the account and does the ordering. The same goes for the email you have on file with us. The email should be the best address to get in touch with you.</p>\r\n<p class=\no-margin>Our account management team periodically reaches out with reminders. Making sure your contact information is up to date is important so that you receive any relevant reminders or communications about using Fluid!</p>\r\n<p class=\no-margin>Also note that if <strong>more than one staff member uses Fluid</strong> for any purpose, it\'s usually recommended that they each have their own login! You can set up additional logins with our <a class=intercom-content-link href=https://shop.Fluid.com/react/account/users target=\\_blank rel=\nofollow noopener noreferrer>Manage Users tool</a> (permission levels are customizable, as well). This makes account recovery much easier in the event that the person who primarily uses your Fluid account is no longer with the practice.</p>',1,'2022-10-19 09:47:00','2022-10-13 09:44:38'),(2,1,'What does Fluid do?','This overview explains what Vetcove is, and how it makes ordering for your veterinary organization fast and easy.','<p class=\no-margin>Fluid is a free platform that lets you compare and buy from all animal health vendors at once. The concept is like Trivago or Amazon, but exclusively for veterinary organizations to order supplies! It\'s one unified catalog that includes all major animal health vendors, giving you a one-stop-shop to research and compare products. You can still place orders with the vendors you already use, and the best part is&hellip; your billing, special pricing, and rep relationships stay exactly the same.</p>\r\n<p class=\no-margin>As of mid-2021, over 13,000 veterinary practices and nonprofits across the country (representing about 42% of all U.S. practices) are already using Fluid to save money and make inventory purchasing frustration-free. Here\'s how Fluid works to streamline your ordering process:</p>\r\n<h2 id=step-1-add-your-vendors data-post-processed=	rue>Step 1: Add your Vendors</h2>\r\n<p class=\no-margin>First, when you sign up with Fluid, you add your existing supplier accounts to Fluid. All of your account information is encrypted and secure &mdash; the Fluid team does not have access to it. It&rsquo;s even written right in our <a class=intercom-content-link href=https://www.Fluid.com/privacy/ target=\\_blank rel=\nofollow noopener noreferrer>privacy policy</a>! Connecting these accounts is necessary because it allows you to see all of your clinic\'s special and rep-negotiated prices with each vendor. You can even enter in your shipping minimum for each vendor so that you&rsquo;re sure to be notified of meeting that total.&nbsp;</p>\r\n<p class=\no-margin>In the past, you may have pared down vendors to make ordering easier, since comparing across so many could be overwhelming and time consuming. With Fluid, you can now shop every vendor quickly, all at once, through a single website!</p>\r\n<p class=\no-margin><br>We encourage clinics to have accounts with as many suppliers as possible. This ensures you have access to the most product options, and lowest prices offered for each one. The net result is that Fluid saves you time, money, and effort!</p>\r\n<h2 id=step-2-search-for-products--add-to-cart data-post-processed=	rue>Step 2: Search for Products &amp; Add to Cart</h2>\r\n<p class=\no-margin>Second, once your vendor accounts are connected, you can begin your search in one unified catalog with every product to see all options. Every product sold by every animal health vendor is here! You can search by product or brand name, manufacturer\'s ID, active ingredient, or any supplier\'s SKU#. &nbsp;&nbsp;</p>\r\n<p class=\no-margin>For each item that is sold by multiple vendors, you\'ll see all vendor options, their prices, stock status, promotions, reviews, notes, and other valuable information. Just click the vendor you\'d like to buy from on Fluid, and the item gets added to the vendor\'s shopping cart! &nbsp;Keep doing this until you\'ve completed your cart.</p>\r\n<h2 id=step-3-manage-carts--complete-checkout data-post-processed=	rue>Step 3: Manage Carts &amp; Complete Checkout</h2>\r\n<p class=\no-margin>With Fluid\'s combined cart, you manage all vendor carts at once, and even move items between carts to help meet shipping minimums. When you\'re ready to check out, since we&rsquo;re already linked to your suppliers\' websites, simply click &ldquo;Proceed to Checkout to complete the orders directly on Fluid.</p>\r\n<p class=\no-margin>When you use Fluid, none of your ordering logistics change at all. Once placed, all orders are processed and shipped directly by the vendor, so you&rsquo;re still billed by your original vendors, and you still get your special pricing, discounts, and rebates. Your vendors also ship the items from the same warehouses as before, so you\'ll receive them just as fast. Your reps will still receive the orders, and your relationships with them don\'t change, since they still receive their commissions when you use Fluid. &nbsp;The only difference is that the process is <em>so much easier</em>, and you ensure you\'re buying from the vendor offering you the lowest price while also being able to compare product reviews and promotions!</p>\r\n<p class=\no-margin><strong>Fluid is not a distributor or buying group. &nbsp;It\'s just a great free software tool we created to make ordering easy and transparent for our fellow clinics.</strong></p>\r\n<p class=\no-margin>With 13,000+ clinics across the country already using Fluid and loving it, we are confident that you will love it too. But don\'t take it from us &mdash; read <a class=intercom-content-link href=https://www.Fluid.com/customers/ target=\\_blank rel=\nofollow noopener noreferrer>testimonials</a> from some of the amazing clinics that use our platform every day!</p>',1,'2022-10-19 09:47:00','2022-10-13 11:11:33'),(9,1,'Connecting your supplier accounts to Fluid','Learn how to connect your vendors to Vetcove to see your special pricing and manage all vendor carts at once.','<p class=\no-margin>You might be aware that veterinary vendors charge a different set of prices to each and every veterinary organization. There is no such thing as a <em>standard price</em>.</p>\r\n<p class=\no-margin>To see your own special pricing in Fluid, you\'ll need to connect your vendor accounts on the <a class=intercom-content-link href=http://www.Fluid.com/react/integration/ target=\\_blank rel=\nofollow noopener noreferrer>Connect Your Suppliers</a> page. &nbsp;Note that you\'ll need to have online access with a given vendor in order to connect your account to Fluid. &nbsp;If you have an active account with a vendor, but aren\'t yet set up to shop online with them, just ask your rep for online access &mdash; they should be able to provide it for you promptly.</p>\r\n<p class=\no-margin>Once a vendor is connected to Fluid, you\'ll be able to see your pricing with them. The pricing will exactly match the pricing you currently receive, including any rep-negotiated or group/GPO discounts.</p>\r\n<p class=\no-margin>Your clinic\'s accounts are secured with 256-bit (online banking level) encryption, along with a number of additional modern security technologies, to ensure their safety. &nbsp;</p>\r\n<h2 id=here-is-an-easy-step-by-step-process-for-adding-your-vendor-accounts-to-Fluid data-post-processed=	rue>Here is an easy, step-by-step process for adding your vendor accounts to Fluid:&nbsp;</h2>\r\n<ol>\r\n<li>\r\n<p class=\no-margin>Log in to your Fluid account at <a class=intercom-content-link href=http://shop.Fluid.com/ target=\\_blank rel=\nofollow noopener noreferrer>shop.Fluid.com</a>. If you do not have a Fluid account yet, you may create one <a class=intercom-content-link href=https://www.Fluid.com/signup target=\\_blank rel=\nofollow noopener noreferrer>here</a>.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Once you\'re logged in, you\'re ready to connect your accounts. You\'ll be prompted to do so during signup, and can return to add more vendors anytime from the <a class=intercom-content-link href=http://www.Fluid.com/react/integration/ target=\\_blank rel=\nofollow noopener noreferrer>Connect Suppliers page</a>. The Connect Supplier page is accessible anytime by clicking on the <em>little person icon </em>at the top-right of the page, and selecting <em>Connect Suppliers </em>from the dropdown menu that appears.&nbsp;</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>When you reach the Connect Suppliers page, click on the logo / icon of the vendor you\'d like to connect. You may connect your account for any of the vendors that are displayed on the Connect Supplier page.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>You will be prompted to enter your credentials for your supplier account. <em>Please note that this is the username and password you would use to log into your <strong>supplier\'s website directly</strong>. If you do not have online access with your supplier, contact your vendor rep, or head to the vendor\'s website directly to request online access. </em></p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>After you have typed your credentials, click on the blue Connect Vendor button.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>If applicable, you will then be prompted to select your clinic\'s specific subaccount/s to shop under when on Fluid. The accounts you select here will be used for syncing your order history, earning rewards on purchases, and tracking loyalty programs.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Congratulations! You have connected a supplier account to Fluid. Repeat this for all of your vendors to be an awesome purchaser and have the best possible experience using Fluid. &nbsp;If you open new vendor accounts, be sure to return to the Connect Supplier page to connect them to Fluid once you\'ve obtained online access.</p>\r\n</li>\r\n</ol>\r\n<p class=\no-margin>When you\'ve connected an account, two years of backdated order history with that vendor will be added to your combined order history on Fluid, along with all of the other vendors you\'ve connected. &nbsp;The purchase data will also be added to your visual analytics, which provides you with useful information that helps you optimize your purchasing behavior.</p>\r\n<h2 id=	rouble-connecting-your-account data-post-processed=	rue>Trouble connecting your account?</h2>\r\n<ul>\r\n<li>\r\n<p class=\no-margin>Invalid Login: Check that you have the most up-to-date credentials by logging into the vendor\'s website directly without using autofill or autologin! You may reset your password on the vendor website or reach out to the vendor if you are unsure of your credentials.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>No Online Shopping Access: Contact the vendor or follow the provided link on the Connect Supplier page to set up online shopping access for your vendor account. Once you\'ve done so, you\'ll be all set to connect on Fluid!</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Check out <a class=intercom-content-link href=https://help.Fluid.com/en/articles/5022034-faq-connecting-suppliers data-is-internal-link=	rue>this FAQ article</a> for additional guidance!</p>\r\n</li>\r\n</ul>\r\n<p class=\no-margin><br><strong>To be able to utilize all of Fluid\'s features and gain access to the best possible prices to compare, we recommend having accounts with as many vendors as possible!</strong></p>\r\n<p class=intercom-align-center no-margin><em>Still have questions about adding your supplier accounts?</em></p>\r\n<p class=intercom-align-center no-margin><em>&nbsp;Feel free to hit the chat icon at the bottom of your screen to speak directly with one of our team members! &nbsp;</em></p>',2,'2022-10-19 09:47:00','2022-10-14 06:48:28'),(11,3,'Guide to Fluid\'s search results page','We explain the features of Vetcove\'s search that help you make informed purchase decisions','<div class=content content__narrow>\r\n<div class=article intercom-force-break>\r\n<article dir=ltr>\r\n<p class=intercom-align-left><em>Before reading this article, you may want to check out our article about </em><a class=intercom-content-link href=http://help.Fluid.com/getting-started-with-Fluid/searching-for-products-and-comparing-options-on-Fluid data-is-internal-link=	rue><em>how to search for products on Fluid</em></a><em>.</em></p>\r\n<h2 id=	he-Fluid-search-results-page class=intercom-align-left data-post-processed=	rue>The Fluid Search Results Page</h2>\r\n<p class=intercom-align-left>Every search on Fluid is filled with powerful insights to help you make more informed purchasing decisions. The article below will explain each component of a Fluid search results page.&nbsp;</p>\r\n<p class=intercom-align-left>We will be using <em>Enrofloxacin Flavor Tablets</em> as an example. The below image identifies each element of a Fluid product listing.</p>\r\n<h2 id=product-information class=intercom-align-left data-post-processed=	rue>Product Information</h2>\r\n<ul>\r\n<li>\r\n<p class=\no-margin><strong>Product Name - </strong>All product names will show the name of the product, the type of product (flavor tablets, capsules, injectable, etc.), the dosage or sizing, and the count size. The subtitle displays the product\'s manufacturer and manufacturer ID#.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin><strong>Unit Pricing - </strong>The final item in the subtitle is a <em>cost-per-unit</em> measure. This helps you compare options across vendors, and across competing products or brands that serve the same purpose.&nbsp;</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin><strong>Attributes - </strong>The Attributes section of the listing includes relevant information about the product, including: main ingredients, size, count, form, and pack type.&nbsp;</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin><strong>Species -</strong> The Species icons indicate which species of animal the product is meant to treat or be used by. Hovering over the Species icons will bring up an information bubble with the name of the species.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin><strong>Details - </strong>Below the product name, you\'ll see a Details button that will allow you to see additional information about the product. This includes an item description, more images, printable SDS if available, and printable brochures if available. This information is provided directly by the manufacturer of the product.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin><strong>Similar - </strong>If the item has any alternatives, a Similar button will open a tab below displaying comparable items from other manufacturers. This tool can be especially helpful when comparing generic products to their name brand counterparts or if the item you need is out of stock or recalled. Clicking the Compare All Similar Items button will bring you to a search result that includes all similar products.&nbsp;</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin><strong>Orders - </strong>&nbsp;If you\'ve previously purchased a given item from any vendor, you will see an Orders button appear next to Details. The number inside the blue bubble indicates the number of previous orders that have included this product. Clicking on the icon will show a graph with information about your past purchases - above the graph, you can toggle between two views. The first view is <em>Purchase Quantity</em>, which shows you the dates you purchased the item as well as the quantity of the item purchased per order. This view allows you to see your purchasing habits for this item over time. The second view is the <em>Purchase Price</em> (click on Purchase Price to change graph), which shows the dates you\'ve purchased the product and the price you paid for the item each time.&nbsp;</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin><strong>Lists </strong>- Our Lists<em> </em>features can help you stay organized and save time, especially if you find yourself ordering some of the same products regularly. Clicking this icon allows you to add or remove this item from any of your shopping lists. You can even create new lists on the fly using the buttons near the bottom! For more information about lists, take a look at our <a class=intercom-content-link href=http://help.Fluid.com/using-Fluid/shopping-lists/guide-using-shopping-lists-on-Fluid data-is-internal-link=	rue>Lists Guide</a>.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin><strong>Notes:</strong> Our notes feature allows you to leave yourself important notes about certain products that only you can see. This is particularly useful when there is specific information about a product that is important to you, or in the event that someone needs to place an order that does not usually do so!</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin><strong>Reviews:</strong> Reviews are a key feature. We allow users to leave product reviews so that they may share their expertise with other users and vice versa. This is a great place to look for additional product information and learn more about product satisfaction.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin><strong>Promotions - </strong>If there are any active promotions that include the item, clicking on the <strong>Promos</strong> button will show all active promotions in a tab under the product listing. You can click into these promotions to view all products included in the promotion.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin><strong>Popularity -</strong> Each listing will display a popularity number in the bottom-right corner of the product section. This number indicates how many veterinary organizations have purchased this specific item in the previous 30 days.</p>\r\n</li>\r\n</ul>\r\n<h2 id=vendor-and-purchase-options class=intercom-align-left data-post-processed=	rue>Vendor and Purchase Options</h2>\r\n<ul>\r\n<li>\r\n<p class=\no-margin><strong>Vendor Options -</strong> In the right-hand column, Fluid displays all vendors that currently sell this particular item. &nbsp;In this column, the name of the vendor appears on the left-hand side if they carry this item in their catalog.</p>\r\n</li>\r\n</ul>\r\n<ul>\r\n<li>\r\n<p class=\no-margin><strong>Your Pricing -</strong> On the far right of this column, you\'ll see the price this vendor currently offers you for this product. &nbsp;You\'ll only see pricing information if you have an active account with the vendor, and you\'ve added them on Fluid. &nbsp;This price is real-time, and accounts for all special and group pricing, so it always matches your vendors\' websites.</p>\r\n</li>\r\n</ul>\r\n<ul>\r\n<li>\r\n<p class=\no-margin><strong>Stock Status - </strong>The center of the column includes a truck icon in one of three colors, which represents the stock status at the vendor. Hovering over the truck provides additional information, such as the number left in stock, and warehouse location information.</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin><strong>Additional Information Icons -</strong> To the right of the stock status icon, there may be a number of different icons that represent additional information the vendor is displaying about the product. &nbsp;The below chart describes all of the available icons and what they indicate.</p>\r\n</li>\r\n</ul>\r\n<p class=intercom-align-center><em>Still have questions regarding the Search Results you are seeing?</em></p>\r\n<p class=intercom-align-center><em>&nbsp;Feel free to hit the chat icon at the bottom of your screen to speak directly with one of our team members!&nbsp;&nbsp;</em></p>\r\n</article>\r\n</div>\r\n</div>',1,'2022-10-19 09:47:00','2022-10-14 08:57:16'),(12,4,'How do I Contact Fluid','Reach us by live chat and email!','<p class=\no-margin>The best way to get in touch with our team is to reach out via the <strong>live chat button</strong> in the bottom right-hand corner of the Fluid page - just start a conversation and we\'ll get in touch as soon as we can!</p>\r\n<p class=\no-margin>You are also welcome to get in touch with us by emailing <a class=intercom-content-link href=mailto:support@Fluid.com target=\\_blank rel=\nofollow noopener noreferrer>supporrt@fluid.vet.</a> We are always happy to chat and answer any questions that you may have! :)</p>',2,'2022-10-19 09:47:00','2022-10-14 09:44:53'),(13,4,'Who can access Fluid','Who can sign up and use Fluid?','<div class=content content__narrow>\r\n<div class=article intercom-force-break>\r\n<article dir=ltr>\r\n<p class=intercom-align-left>Fluid is accessible only to verified staff of veterinary clinics (and other veterinary organizations) in the United States, including veterinarians, technicians, practice managers, and inventory managers. Only staff ordering on behalf of a verifiable U.S. licensed veterinarian are permitted to access and use Fluid. Additionally, only vendor accounts that are active and valid will be capable of connecting to Fluid. Permitted organizations include:</p>\r\n<ul>\r\n<li>\r\n<p class=\no-margin>Veterinary Clinics</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Emergency and Specialty Hospitals</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Universities</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Zoos</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Aqariums</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>SPCAs</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Shelters</p>\r\n</li>\r\n<li>\r\n<p class=\no-margin>Independent Veterinarians</p>\r\n</li>\r\n</ul>\r\n<p class=intercom-align-left>Verified manufacturers of animal health products may also be granted limited access to the platform to manage their own products\' pages, but will not be able to search the catalog.&nbsp;</p>\r\n</article>\r\n</div>\r\n</div>',2,'2022-10-19 09:33:08','2022-10-14 09:46:42'),(14,5,'','','<p>This Privacy Policy describes how thevet.group (the &ldquo;Site&rdquo; or &ldquo;we&rdquo;) collects, uses, and discloses your Personal Information when you visit or make a purchase from the Site.</p>\r\n<p>Collecting Personal Information When you visit the Site, we collect certain information about your device, your interaction with the Site, and information necessary to process your purchases. We may also collect additional information if you contact us for customer support. In this Privacy Policy, we refer to any information that can uniquely identify an individual (including the information below) as &ldquo;Personal Information&rdquo;. See the list below for more information about what Personal Information we collect and why.</p>\r\n<h6>Device Information</h6>\r\n<p>Examples of Personal Information collected: a version of web browser, IP address, time zone, cookie information, what sites or products you view, search terms, and how you interact with the Site. Purpose of collection: to load the Site accurately for you, and to perform analytics on Site usage to optimize our Site. Source of collection: Collected automatically when you access our Site using cookies, log files, web beacons, tags, or pixels Disclosure for a business purpose: shared with our processor Shopify Order information</p>\r\n<p>Examples of Personal Information collected: name, billing address, shipping address, payment information (including credit card numbers, email address, and phone number. Purpose of collection: to provide products or services to you to fulfil our contract, to process your payment information, arrange for shipping, and provide you with invoices and/or order confirmations, communicate with you, screen our orders for potential risk or fraud, and when in line with the preferences you have shared with us, provide you with information or advertising relating to our products or services. Source of collection: collected from you. Disclosure for a business purpose: shared with our processor Shopify, Payment Gateway.</p>\r\n<h6>MINORS</h6>\r\n<p>The Site is not intended for individuals under the age of 18. We do not intentionally collect Personal Information from children. If you are the parent or guardian and believe your child has provided us with Personal Information, please contact us at the address below to request deletion.</p>\r\n<h6>Sharing Personal Information</h6>\r\n<p>We share your Personal Information with service providers to help us provide our services and fulfil our contracts with you, as described above. For example:</p>\r\n<p>We use Shopify to power our online store. You can read more about how Shopify uses your Personal Information here: https://www.shopify.com/legal/privacy. We may share your Personal Information to comply with applicable laws and regulations, to respond to a subpoena, search warrant or other lawful requests for information we receive, or to otherwise protect our rights.</p>\r\n<h6>BEHAVIOURAL ADVERTISING</h6>\r\n<p>As described above, we use your Personal Information to provide you with targeted advertisements or marketing communications we believe may be of interest to you. For example:</p>\r\n<p>We use Google Analytics to help us understand how our customers use the Site. You can read more about how Google uses your Personal Information here: https://policies.google.com/privacy?hl=en.You can also opt-out of Google Analytics here: https://tools.google.com/dlpage/gaoptout. We share information about your use of the Site, your purchases, and your interaction with our ads on other websites with our advertising partners. We collect and share some of this information directly with our advertising partners, and in some cases through the use of cookies or other similar technologies (which you may consent to, depending on your location). For more information about how targeted advertising works, you can visit the Network Advertising Initiative&rsquo;s (&ldquo;NAI&rdquo;) educational page at http://www.networkadvertising.org/understanding-online-advertising/how-does-it-work.</p>\r\n<p>Additionally, you can opt-out of some of these services by visiting the Digital Advertising Alliance&rsquo;s opt-out portal at http://optout.aboutads.info.</p>\r\n<h6>Using Personal Information</h6>\r\n<p>We use your Personal Information to provide our services to you, which includes: offering products for sale, processing payments, shipping and fulfilment of your order, and keeping you up to date on new products, services, and offers.</p>\r\n<h6>LAWFUL BASIS</h6>\r\n<p>Pursuant to the General Data Protection Regulation (&ldquo;GDPR&rdquo;), if you are a resident of the European Economic Area (&ldquo;EEA&rdquo;), we process your personal information under the following lawful bases:</p>',2,'2022-10-17 13:40:57','2022-10-17 15:40:57'),(18,6,'Terms & Conditions','Terms & Conditions','<p>This Privacy Policy describes how thevet.group (the &ldquo;Site&rdquo; or &ldquo;we&rdquo;) collects, uses, and discloses your Personal Information when you visit or make a purchase from the Site.</p>\r\n<p>Collecting Personal Information When you visit the Site, we collect certain information about your device, your interaction with the Site, and information necessary to process your purchases. We may also collect additional information if you contact us for customer support. In this Privacy Policy, we refer to any information that can uniquely identify an individual (including the information below) as &ldquo;Personal Information&rdquo;. See the list below for more information about what Personal Information we collect and why.</p>\r\n<h6>Device Information</h6>\r\n<p>Examples of Personal Information collected: a version of web browser, IP address, time zone, cookie information, what sites or products you view, search terms, and how you interact with the Site. Purpose of collection: to load the Site accurately for you, and to perform analytics on Site usage to optimize our Site. Source of collection: Collected automatically when you access our Site using cookies, log files, web beacons, tags, or pixels Disclosure for a business purpose: shared with our processor Shopify Order information</p>\r\n<p>Examples of Personal Information collected: name, billing address, shipping address, payment information (including credit card numbers, email address, and phone number. Purpose of collection: to provide products or services to you to fulfil our contract, to process your payment information, arrange for shipping, and provide you with invoices and/or order confirmations, communicate with you, screen our orders for potential risk or fraud, and when in line with the preferences you have shared with us, provide you with information or advertising relating to our products or services. Source of collection: collected from you. Disclosure for a business purpose: shared with our processor Shopify, Payment Gateway.</p>\r\n<h6>MINORS</h6>\r\n<p>The Site is not intended for individuals under the age of 18. We do not intentionally collect Personal Information from children. If you are the parent or guardian and believe your child has provided us with Personal Information, please contact us at the address below to request deletion.</p>\r\n<h6>Sharing Personal Information</h6>\r\n<p>We share your Personal Information with service providers to help us provide our services and fulfil our contracts with you, as described above. For example:</p>\r\n<p>We use Shopify to power our online store. You can read more about how Shopify uses your Personal Information here: https://www.shopify.com/legal/privacy. We may share your Personal Information to comply with applicable laws and regulations, to respond to a subpoena, search warrant or other lawful requests for information we receive, or to otherwise protect our rights.</p>\r\n<h6>BEHAVIOURAL ADVERTISING</h6>\r\n<p>As described above, we use your Personal Information to provide you with targeted advertisements or marketing communications we believe may be of interest to you. For example:</p>\r\n<p>We use Google Analytics to help us understand how our customers use the Site. You can read more about how Google uses your Personal Information here: https://policies.google.com/privacy?hl=en.You can also opt-out of Google Analytics here: https://tools.google.com/dlpage/gaoptout. We share information about your use of the Site, your purchases, and your interaction with our ads on other websites with our advertising partners. We collect and share some of this information directly with our advertising partners, and in some cases through the use of cookies or other similar technologies (which you may consent to, depending on your location). For more information about how targeted advertising works, you can visit the Network Advertising Initiative&rsquo;s (&ldquo;NAI&rdquo;) educational page at http://www.networkadvertising.org/understanding-online-advertising/how-does-it-work.</p>\r\n<p>Additionally, you can opt-out of some of these services by visiting the Digital Advertising Alliance&rsquo;s opt-out portal at http://optout.aboutads.info.</p>\r\n<h6>Using Personal Information</h6>\r\n<p>We use your Personal Information to provide our services to you, which includes: offering products for sale, processing payments, shipping and fulfilment of your order, and keeping you up to date on new products, services, and offers.</p>\r\n<h6>LAWFUL BASIS</h6>\r\n<p>Pursuant to the General Data Protection Regulation (&ldquo;GDPR&rdquo;), if you are a resident of the European Economic Area (&ldquo;EEA&rdquo;), we process your personal information under the following lawful bases:</p>',2,'2022-10-18 06:19:38','2022-10-18 08:19:38'),(19,7,'About Fluid','About Fluid','<h5>DELIVERING EXCELLENCE</h5>\r\n<p>TVG was born out of a desire to make a difference to the Animal Health landscape by an extremely experienced management team with over 60 years of combined expertise in the region across multiple species. Through innovation, education, trust and perseverance we are passionate about driving change and delivering excellence in the Animal Health industry.</p>\r\n<h5>The Three Pillars of our business</h5>\r\n<p>We prioritize the needs of our&nbsp;<strong>Customers, People and Suppliers</strong>&nbsp;above all else, through open and honest communication to maintain a healthy balance to meet and exceed their expectations.</p>\r\n<p>From this flows the success of the overall business and builds enduring value for the Animal Health Industry in our chosen markets.</p>\r\n<h5>Four Important Values</h5>\r\n<p><strong>People</strong>&nbsp;- We&rsquo;re all in this together. Empowering, trusting and believing in ourselves and our team. Together we can achieve anything.</p>\r\n<p><strong>Sustainability</strong>&nbsp;- To ensure sustainability for future generations, with an ethical and moral approach to animal health.</p>\r\n<p><strong>Excellence</strong>&nbsp;- What we choose to do we do to the best of our ability. We strive for continuous improvement in search of excellence.</p>\r\n<p><strong>Perseverance</strong>&nbsp;- Never give up on something you believe in.</p>\r\n<h5>Services and Products</h5>\r\n<p><strong>&bull; Identification</strong>&nbsp;- Microchipping and Tagging, Traceability projects to enhance national food safety</p>\r\n<p><strong>&bull; Pharmaceuticals</strong>&nbsp;- World-class solutions in managing the supply chain of pharma and vaccines</p>\r\n<p><strong>&bull; Education / CPD</strong>&nbsp;- Delivering quality educational events</p>\r\n<p><strong>&bull; Supplements</strong>&nbsp;- Nutrition and health</p>\r\n<p><strong>&bull; Feed</strong>&nbsp;&ndash; Market-leading equine nutrition and performance product</p>\r\n<p><strong>ENTHUSIASM | PASSION | PRIDE</strong></p>',2,'2022-10-18 06:48:48','2022-10-18 08:42:58');
/*!40000 ALTER TABLE `article_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `articles`
--

DROP TABLE IF EXISTS `articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `articles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `page_id` int NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL,
  `description` text,
  `icon` varchar(255) DEFAULT NULL,
  `article_count` int NOT NULL DEFAULT '0',
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  `is_multi` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles`
--

LOCK TABLES `articles` WRITE;
/*!40000 ALTER TABLE `articles` DISABLE KEYS */;
INSERT INTO `articles` VALUES (1,2,'Getting Started with Fluid','New to Fluid? Let\'s get started.','fas fa-user-check',3,'2022-10-18 05:12:20','2022-10-13 07:45:52',1),(3,2,'Using Fluid','Learn the ins and outs of using Vetcove for your ordering','fas fa-book-open',1,'2022-10-18 05:37:16','2022-10-13 16:44:14',1),(4,2,'FAQs: About Fluid','Answers to some common questions about what Fluid is & how we do what we do','fa-regular fa-comment-question',2,'2022-10-24 05:50:01','2022-10-13 16:46:44',1),(5,3,'Privacy Policy','','',1,'2022-10-17 13:40:57','2022-10-17 15:36:21',0),(6,4,'Terms & Condtions','','',1,'2022-10-18 06:19:38','2022-10-18 08:17:02',0),(7,5,'About Us','','',1,'2022-10-18 06:42:58','2022-10-18 08:38:38',0);
/*!40000 ALTER TABLE `articles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `availability_tracker`
--

DROP TABLE IF EXISTS `availability_tracker`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `availability_tracker` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int DEFAULT NULL,
  `distributor_id` int NOT NULL,
  `clinic_id` int DEFAULT NULL,
  `communication_id` int DEFAULT NULL,
  `is_sent` tinyint(1) NOT NULL,
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `distributor_id` (`distributor_id`),
  KEY `clinic_id` (`clinic_id`),
  KEY `communication_id` (`communication_id`),
  CONSTRAINT `availability_tracker_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `availability_tracker_ibfk_2` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`),
  CONSTRAINT `availability_tracker_ibfk_3` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  CONSTRAINT `availability_tracker_ibfk_4` FOREIGN KEY (`communication_id`) REFERENCES `clinic_communication_methods` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `availability_tracker`
--

LOCK TABLES `availability_tracker` WRITE;
/*!40000 ALTER TABLE `availability_tracker` DISABLE KEYS */;
/*!40000 ALTER TABLE `availability_tracker` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `banners`
--

DROP TABLE IF EXISTS `banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banners` (
  `id` int NOT NULL AUTO_INCREMENT,
  `page_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `caption` text,
  `alt` varchar(255) NOT NULL,
  `is_published` int NOT NULL DEFAULT '0',
  `is_default` int NOT NULL DEFAULT '0',
  `order_by` int NOT NULL,
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_banners_1_idx` (`page_id`),
  CONSTRAINT `banners_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `banners`
--

LOCK TABLES `banners` WRITE;
/*!40000 ALTER TABLE `banners` DISABLE KEYS */;
/*!40000 ALTER TABLE `banners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `basket_items`
--

DROP TABLE IF EXISTS `basket_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `basket_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `basket_id` int NOT NULL,
  `product_id` int NOT NULL,
  `distributor_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `qty` int NOT NULL DEFAULT '0',
  `unit_price` float(9,2) NOT NULL,
  `total` float(9,2) NOT NULL,
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  `item_id` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `basket_id_fk_idx` (`basket_id`),
  KEY `product_id_fk_idx` (`product_id`),
  KEY `distributor_id_fk_idx` (`distributor_id`),
  CONSTRAINT `fk_basket_id` FOREIGN KEY (`basket_id`) REFERENCES `baskets` (`id`),
  CONSTRAINT `fk_distributor_id` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`),
  CONSTRAINT `fk_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=299 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `basket_items`
--

LOCK TABLES `basket_items` WRITE;
/*!40000 ALTER TABLE `basket_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `basket_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `baskets`
--

DROP TABLE IF EXISTS `baskets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `baskets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinic_id` int DEFAULT NULL,
  `distributor_id` int DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` double DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `saved_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` int NOT NULL DEFAULT '0',
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_DCFB21EFCC22AD4` (`clinic_id`),
  KEY `IDX_DCFB21EF2D863A58` (`distributor_id`),
  CONSTRAINT `FK_DCFB21EF2D863A58` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`),
  CONSTRAINT `FK_DCFB21EFCC22AD4` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `baskets`
--

LOCK TABLES `baskets` WRITE;
/*!40000 ALTER TABLE `baskets` DISABLE KEYS */;
/*!40000 ALTER TABLE `baskets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `is_root` int NOT NULL DEFAULT '0',
  `root_id` int DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `child_ids` json DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `modified` timestamp NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories1`
--

DROP TABLE IF EXISTS `categories1`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories1` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `product_count` int DEFAULT '0',
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `search_idx2` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories1`
--

LOCK TABLES `categories1` WRITE;
/*!40000 ALTER TABLE `categories1` DISABLE KEYS */;
INSERT INTO `categories1` VALUES (86,'Pharmaceuticals','[]',NULL,NULL,'2022-10-26 07:24:41','2022-10-26 09:24:41');
/*!40000 ALTER TABLE `categories1` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories2`
--

DROP TABLE IF EXISTS `categories2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories2` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category1_id` int DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `tags_array` varchar(45) DEFAULT NULL,
  `product_count` int DEFAULT '0',
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `category1_id` (`category1_id`),
  FULLTEXT KEY `search_idx3` (`slug`),
  CONSTRAINT `categories2_ibfk_1` FOREIGN KEY (`category1_id`) REFERENCES `categories1` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories2`
--

LOCK TABLES `categories2` WRITE;
/*!40000 ALTER TABLE `categories2` DISABLE KEYS */;
INSERT INTO `categories2` VALUES (61,86,'Antibiotics','[]',NULL,'N;',NULL,'2022-10-26 07:24:57','2022-10-26 09:24:57');
/*!40000 ALTER TABLE `categories2` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories3`
--

DROP TABLE IF EXISTS `categories3`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories3` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category2_id` int DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `tags` json DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `tags_array` varchar(255) DEFAULT NULL,
  `product_count` int DEFAULT '0',
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `category2_id` (`category2_id`),
  FULLTEXT KEY `search_idx4` (`slug`),
  CONSTRAINT `categories3_ibfk_1` FOREIGN KEY (`category2_id`) REFERENCES `categories2` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories3`
--

LOCK TABLES `categories3` WRITE;
/*!40000 ALTER TABLE `categories3` DISABLE KEYS */;
INSERT INTO `categories3` VALUES (43,61,'Sub 1.1','[]',NULL,'N;',NULL,'2022-10-26 07:25:35','2022-10-26 09:25:35');
/*!40000 ALTER TABLE `categories3` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories_bak`
--

DROP TABLE IF EXISTS `categories_bak`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories_bak` (
  `id` int NOT NULL AUTO_INCREMENT,
  `parent_id` int NOT NULL DEFAULT '0',
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories_bak`
--

LOCK TABLES `categories_bak` WRITE;
/*!40000 ALTER TABLE `categories_bak` DISABLE KEYS */;
/*!40000 ALTER TABLE `categories_bak` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_messages`
--

DROP TABLE IF EXISTS `chat_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orders_id` int NOT NULL,
  `distributor_id` int NOT NULL,
  `message` text NOT NULL,
  `is_distributor` int NOT NULL DEFAULT '0',
  `is_clinic` int NOT NULL,
  `modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id_fk_idx` (`orders_id`),
  CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`orders_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_messages`
--

LOCK TABLES `chat_messages` WRITE;
/*!40000 ALTER TABLE `chat_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chat_participants`
--

DROP TABLE IF EXISTS `chat_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_participants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orders_id` int NOT NULL,
  `distributor_id` int NOT NULL,
  `clinic_id` int NOT NULL DEFAULT '0',
  `distributor_is_typing` int DEFAULT '0',
  `clinic_is_typing` int DEFAULT '0',
  `modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `conversation_id` (`orders_id`),
  KEY `distributor_id` (`distributor_id`),
  KEY `clinic_id` (`clinic_id`),
  CONSTRAINT `chat_participants_ibfk_1` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`),
  CONSTRAINT `clinic_id_fk` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  CONSTRAINT `order_id_fk` FOREIGN KEY (`orders_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chat_participants`
--

LOCK TABLES `chat_participants` WRITE;
/*!40000 ALTER TABLE `chat_participants` DISABLE KEYS */;
/*!40000 ALTER TABLE `chat_participants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clinic_communication_methods`
--

DROP TABLE IF EXISTS `clinic_communication_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinic_communication_methods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinic_id` int DEFAULT NULL,
  `communication_method_id` int DEFAULT NULL,
  `send_to` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intl_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1',
  `is_default` int NOT NULL DEFAULT '0',
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `communication_method_id` (`communication_method_id`),
  KEY `clinic_id_fk_idx` (`clinic_id`),
  CONSTRAINT `clinic_communication_methods_ibfk_1` FOREIGN KEY (`communication_method_id`) REFERENCES `communication_methods` (`id`),
  CONSTRAINT `clinic_communication_methods_ibfk_2` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinic_communication_methods`
--

LOCK TABLES `clinic_communication_methods` WRITE;
/*!40000 ALTER TABLE `clinic_communication_methods` DISABLE KEYS */;
/*!40000 ALTER TABLE `clinic_communication_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clinic_products`
--

DROP TABLE IF EXISTS `clinic_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinic_products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinic_id` int NOT NULL,
  `product_id` int NOT NULL,
  `distributor_id` int NOT NULL,
  `item_id` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `qty` int NOT NULL,
  `unit_price` float(9,2) NOT NULL,
  `total` float(9,2) DEFAULT NULL,
  `saved_by` varchar(255) NOT NULL,
  `modified` timestamp NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `clinic_id` (`clinic_id`),
  KEY `product_id` (`product_id`),
  KEY `distributor_id` (`distributor_id`),
  CONSTRAINT `clinic_products_ibfk_1` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  CONSTRAINT `clinic_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `clinic_products_ibfk_3` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=403 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinic_products`
--

LOCK TABLES `clinic_products` WRITE;
/*!40000 ALTER TABLE `clinic_products` DISABLE KEYS */;
/*!40000 ALTER TABLE `clinic_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clinic_user_permissions`
--

DROP TABLE IF EXISTS `clinic_user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinic_user_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinic_id` int NOT NULL,
  `user_id` int NOT NULL,
  `permission_id` int NOT NULL,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `clinic_id_fk_idx` (`clinic_id`),
  KEY `permission_user_id_fk` (`user_id`),
  KEY `permission_id_fk_idx` (`permission_id`),
  CONSTRAINT `permission_clinic_id_fk` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  CONSTRAINT `permission_id_fk` FOREIGN KEY (`permission_id`) REFERENCES `user_permissions` (`id`),
  CONSTRAINT `permission_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `clinic_users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1745 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinic_user_permissions`
--

LOCK TABLES `clinic_user_permissions` WRITE;
/*!40000 ALTER TABLE `clinic_user_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `clinic_user_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clinic_users`
--

DROP TABLE IF EXISTS `clinic_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinic_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinic_id` int DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hashed_email` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intl_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review_username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `roles` json NOT NULL,
  `reset_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL,
  `modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_9D070CDACC22AD4` (`clinic_id`),
  CONSTRAINT `FK_9D070CDACC22AD4` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinic_users`
--

LOCK TABLES `clinic_users` WRITE;
/*!40000 ALTER TABLE `clinic_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `clinic_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clinics`
--

DROP TABLE IF EXISTS `clinics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `country_id` int DEFAULT NULL,
  `clinic_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `iso_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intl_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hashed_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `clinic_address_idx` (`country_id`),
  CONSTRAINT `clinics_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinics`
--

LOCK TABLES `clinics` WRITE;
/*!40000 ALTER TABLE `clinics` DISABLE KEYS */;
/*!40000 ALTER TABLE `clinics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `communication_methods`
--

DROP TABLE IF EXISTS `communication_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `communication_methods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `communication_methods`
--

LOCK TABLES `communication_methods` WRITE;
/*!40000 ALTER TABLE `communication_methods` DISABLE KEYS */;
INSERT INTO `communication_methods` VALUES (1,'In App Notifications','2022-02-25 07:16:23','2022-02-25 07:16:23'),(2,'Send an Email','2022-02-25 07:16:23','2022-02-25 07:16:23'),(3,'Send a Text','2022-02-25 07:16:23','2022-02-25 07:16:23');
/*!40000 ALTER TABLE `communication_methods` ENABLE KEYS */;
UNLOCK TABLES;
--

LOCK TABLES `communication_methods` WRITE;
/*!40000 ALTER TABLE `communication_methods` DISABLE KEYS */;
/*!40000 ALTER TABLE `communication_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `countries`
--

DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `phone` int NOT NULL,
  `code` char(2) NOT NULL,
  `currency` varchar(45) DEFAULT NULL,
  `name` varchar(80) NOT NULL,
  `is_active` int NOT NULL DEFAULT '0',
  `modified` timestamp NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=253 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `countries`
--

LOCK TABLES `countries` WRITE;
/*!40000 ALTER TABLE `countries` DISABLE KEYS */;
INSERT INTO `countries` VALUES (1,93,'AF',NULL,'Afghanistan',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(2,358,'AX',NULL,'Aland Islands',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(3,355,'AL',NULL,'Albania',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(4,213,'DZ',NULL,'Algeria',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(5,1684,'AS',NULL,'American Samoa',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(6,376,'AD',NULL,'Andorra',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(7,244,'AO',NULL,'Angola',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(8,1264,'AI',NULL,'Anguilla',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(9,672,'AQ',NULL,'Antarctica',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(10,1268,'AG',NULL,'Antigua and Barbuda',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(11,54,'AR',NULL,'Argentina',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(12,374,'AM',NULL,'Armenia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(13,297,'AW',NULL,'Aruba',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(14,61,'AU',NULL,'Australia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(15,43,'AT',NULL,'Austria',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(16,994,'AZ',NULL,'Azerbaijan',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(17,1242,'BS',NULL,'Bahamas',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(18,973,'BH',NULL,'Bahrain',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(19,880,'BD',NULL,'Bangladesh',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(20,1246,'BB',NULL,'Barbados',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(21,375,'BY',NULL,'Belarus',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(22,32,'BE',NULL,'Belgium',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(23,501,'BZ',NULL,'Belize',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(24,229,'BJ',NULL,'Benin',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(25,1441,'BM',NULL,'Bermuda',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(26,975,'BT',NULL,'Bhutan',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(27,591,'BO',NULL,'Bolivia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(28,599,'BQ',NULL,'Bonaire, Sint Eustatius and Saba',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(29,387,'BA',NULL,'Bosnia and Herzegovina',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(30,267,'BW',NULL,'Botswana',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(31,55,'BV',NULL,'Bouvet Island',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(32,55,'BR',NULL,'Brazil',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(33,246,'IO',NULL,'British Indian Ocean Territory',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(34,673,'BN',NULL,'Brunei Darussalam',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(35,359,'BG',NULL,'Bulgaria',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(36,226,'BF',NULL,'Burkina Faso',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(37,257,'BI',NULL,'Burundi',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(38,855,'KH',NULL,'Cambodia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(39,237,'CM',NULL,'Cameroon',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(40,1,'CA',NULL,'Canada',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(41,238,'CV',NULL,'Cape Verde',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(42,1345,'KY',NULL,'Cayman Islands',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(43,236,'CF',NULL,'Central African Republic',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(44,235,'TD',NULL,'Chad',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(45,56,'CL',NULL,'Chile',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(46,86,'CN',NULL,'China',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(47,61,'CX',NULL,'Christmas Island',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(48,672,'CC',NULL,'Cocos (Keeling) Islands',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(49,57,'CO',NULL,'Colombia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(50,269,'KM',NULL,'Comoros',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(51,242,'CG',NULL,'Congo',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(52,242,'CD',NULL,'Congo, Democratic Republic of the Congo',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(53,682,'CK',NULL,'Cook Islands',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(54,506,'CR',NULL,'Costa Rica',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(55,225,'CI',NULL,'Cote D\'Ivoire',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(56,385,'HR',NULL,'Croatia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(57,53,'CU',NULL,'Cuba',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(58,599,'CW',NULL,'Curacao',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(59,357,'CY',NULL,'Cyprus',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(60,420,'CZ',NULL,'Czech Republic',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(61,45,'DK',NULL,'Denmark',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(62,253,'DJ',NULL,'Djibouti',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(63,1767,'DM',NULL,'Dominica',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(64,1809,'DO',NULL,'Dominican Republic',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(65,593,'EC',NULL,'Ecuador',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(66,20,'EG',NULL,'Egypt',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(67,503,'SV',NULL,'El Salvador',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(68,240,'GQ',NULL,'Equatorial Guinea',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(69,291,'ER',NULL,'Eritrea',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(70,372,'EE',NULL,'Estonia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(71,251,'ET',NULL,'Ethiopia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(72,500,'FK',NULL,'Falkland Islands (Malvinas)',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(73,298,'FO',NULL,'Faroe Islands',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(74,679,'FJ',NULL,'Fiji',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(75,358,'FI',NULL,'Finland',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(76,33,'FR',NULL,'France',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(77,594,'GF',NULL,'French Guiana',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(78,689,'PF',NULL,'French Polynesia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(79,262,'TF',NULL,'French Southern Territories',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(80,241,'GA',NULL,'Gabon',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(81,220,'GM',NULL,'Gambia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(82,995,'GE',NULL,'Georgia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(83,49,'DE',NULL,'Germany',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(84,233,'GH',NULL,'Ghana',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(85,350,'GI',NULL,'Gibraltar',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(86,30,'GR',NULL,'Greece',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(87,299,'GL',NULL,'Greenland',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(88,1473,'GD',NULL,'Grenada',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(89,590,'GP',NULL,'Guadeloupe',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(90,1671,'GU',NULL,'Guam',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(91,502,'GT',NULL,'Guatemala',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(92,44,'GG',NULL,'Guernsey',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(93,224,'GN',NULL,'Guinea',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(94,245,'GW',NULL,'Guinea-Bissau',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(95,592,'GY',NULL,'Guyana',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(96,509,'HT',NULL,'Haiti',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(97,0,'HM',NULL,'Heard Island and Mcdonald Islands',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(98,39,'VA',NULL,'Holy See (Vatican City State)',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(99,504,'HN',NULL,'Honduras',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(100,852,'HK',NULL,'Hong Kong',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(101,36,'HU',NULL,'Hungary',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(102,354,'IS',NULL,'Iceland',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(103,91,'IN',NULL,'India',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(104,62,'ID',NULL,'Indonesia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(105,98,'IR',NULL,'Iran, Islamic Republic of',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(106,964,'IQ',NULL,'Iraq',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(107,353,'IE',NULL,'Ireland',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(108,44,'IM',NULL,'Isle of Man',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(109,972,'IL',NULL,'Israel',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(110,39,'IT',NULL,'Italy',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(111,1876,'JM',NULL,'Jamaica',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(112,81,'JP',NULL,'Japan',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(113,44,'JE',NULL,'Jersey',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(114,962,'JO',NULL,'Jordan',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(115,7,'KZ',NULL,'Kazakhstan',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(116,254,'KE',NULL,'Kenya',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(117,686,'KI',NULL,'Kiribati',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(118,850,'KP',NULL,'Korea, Democratic People\'s Republic of',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(119,82,'KR',NULL,'Korea, Republic of',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(120,381,'XK',NULL,'Kosovo',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(121,965,'KW',NULL,'Kuwait',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(122,996,'KG',NULL,'Kyrgyzstan',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(123,856,'LA',NULL,'Lao People\'s Democratic Republic',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(124,371,'LV',NULL,'Latvia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(125,961,'LB',NULL,'Lebanon',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(126,266,'LS',NULL,'Lesotho',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(127,231,'LR',NULL,'Liberia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(128,218,'LY',NULL,'Libyan Arab Jamahiriya',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(129,423,'LI',NULL,'Liechtenstein',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(130,370,'LT',NULL,'Lithuania',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(131,352,'LU',NULL,'Luxembourg',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(132,853,'MO',NULL,'Macao',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(133,389,'MK',NULL,'Macedonia, the Former Yugoslav Republic of',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(134,261,'MG',NULL,'Madagascar',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(135,265,'MW',NULL,'Malawi',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(136,60,'MY',NULL,'Malaysia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(137,960,'MV',NULL,'Maldives',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(138,223,'ML',NULL,'Mali',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(139,356,'MT',NULL,'Malta',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(140,692,'MH',NULL,'Marshall Islands',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(141,596,'MQ',NULL,'Martinique',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(142,222,'MR',NULL,'Mauritania',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(143,230,'MU',NULL,'Mauritius',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(144,269,'YT',NULL,'Mayotte',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(145,52,'MX',NULL,'Mexico',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(146,691,'FM',NULL,'Micronesia, Federated States of',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(147,373,'MD',NULL,'Moldova, Republic of',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(148,377,'MC',NULL,'Monaco',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(149,976,'MN',NULL,'Mongolia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(150,382,'ME',NULL,'Montenegro',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(151,1664,'MS',NULL,'Montserrat',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(152,212,'MA',NULL,'Morocco',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(153,258,'MZ',NULL,'Mozambique',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(154,95,'MM',NULL,'Myanmar',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(155,264,'NA',NULL,'Namibia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(156,674,'NR',NULL,'Nauru',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(157,977,'NP',NULL,'Nepal',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(158,31,'NL',NULL,'Netherlands',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(159,599,'AN',NULL,'Netherlands Antilles',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(160,687,'NC',NULL,'New Caledonia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(161,64,'NZ',NULL,'New Zealand',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(162,505,'NI',NULL,'Nicaragua',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(163,227,'NE',NULL,'Niger',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(164,234,'NG',NULL,'Nigeria',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(165,683,'NU',NULL,'Niue',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(166,672,'NF',NULL,'Norfolk Island',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(167,1670,'MP',NULL,'Northern Mariana Islands',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(168,47,'NO',NULL,'Norway',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(169,968,'OM',NULL,'Oman',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(170,92,'PK',NULL,'Pakistan',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(171,680,'PW',NULL,'Palau',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(172,970,'PS',NULL,'Palestinian Territory, Occupied',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(173,507,'PA',NULL,'Panama',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(174,675,'PG',NULL,'Papua New Guinea',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(175,595,'PY',NULL,'Paraguay',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(176,51,'PE',NULL,'Peru',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(177,63,'PH',NULL,'Philippines',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(178,64,'PN',NULL,'Pitcairn',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(179,48,'PL',NULL,'Poland',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(180,351,'PT',NULL,'Portugal',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(181,1787,'PR',NULL,'Puerto Rico',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(182,974,'QA',NULL,'Qatar',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(183,262,'RE',NULL,'Reunion',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(184,40,'RO',NULL,'Romania',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(185,70,'RU',NULL,'Russian Federation',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(186,250,'RW',NULL,'Rwanda',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(187,590,'BL',NULL,'Saint Barthelemy',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(188,290,'SH',NULL,'Saint Helena',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(189,1869,'KN',NULL,'Saint Kitts and Nevis',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(190,1758,'LC',NULL,'Saint Lucia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(191,590,'MF',NULL,'Saint Martin',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(192,508,'PM',NULL,'Saint Pierre and Miquelon',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(193,1784,'VC',NULL,'Saint Vincent and the Grenadines',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(194,684,'WS',NULL,'Samoa',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(195,378,'SM',NULL,'San Marino',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(196,239,'ST',NULL,'Sao Tome and Principe',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(197,966,'SA',NULL,'Saudi Arabia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(198,221,'SN',NULL,'Senegal',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(199,381,'RS',NULL,'Serbia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(200,381,'CS',NULL,'Serbia and Montenegro',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(201,248,'SC',NULL,'Seychelles',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(202,232,'SL',NULL,'Sierra Leone',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(203,65,'SG',NULL,'Singapore',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(204,1,'SX',NULL,'Sint Maarten',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(205,421,'SK',NULL,'Slovakia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(206,386,'SI',NULL,'Slovenia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(207,677,'SB',NULL,'Solomon Islands',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(208,252,'SO',NULL,'Somalia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(209,27,'ZA',NULL,'South Africa',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(210,500,'GS',NULL,'South Georgia and the South Sandwich Islands',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(211,211,'SS',NULL,'South Sudan',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(212,34,'ES',NULL,'Spain',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(213,94,'LK',NULL,'Sri Lanka',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(214,249,'SD',NULL,'Sudan',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(215,597,'SR',NULL,'Suriname',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(216,47,'SJ',NULL,'Svalbard and Jan Mayen',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(217,268,'SZ',NULL,'Swaziland',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(218,46,'SE',NULL,'Sweden',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(219,41,'CH',NULL,'Switzerland',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(220,963,'SY',NULL,'Syrian Arab Republic',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(221,886,'TW',NULL,'Taiwan, Province of China',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(222,992,'TJ',NULL,'Tajikistan',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(223,255,'TZ',NULL,'Tanzania, United Republic of',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(224,66,'TH',NULL,'Thailand',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(225,670,'TL',NULL,'Timor-Leste',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(226,228,'TG',NULL,'Togo',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(227,690,'TK',NULL,'Tokelau',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(228,676,'TO',NULL,'Tonga',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(229,1868,'TT',NULL,'Trinidad and Tobago',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(230,216,'TN',NULL,'Tunisia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(231,90,'TR',NULL,'Turkey',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(232,7370,'TM',NULL,'Turkmenistan',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(233,1649,'TC',NULL,'Turks and Caicos Islands',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(234,688,'TV',NULL,'Tuvalu',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(235,256,'UG',NULL,'Uganda',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(236,380,'UA',NULL,'Ukraine',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(237,971,'AE','AED','United Arab Emirates',1,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(238,44,'GB',NULL,'United Kingdom',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(239,1,'US',NULL,'United States',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(240,1,'UM',NULL,'United States Minor Outlying Islands',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(241,598,'UY',NULL,'Uruguay',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(242,998,'UZ',NULL,'Uzbekistan',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(243,678,'VU',NULL,'Vanuatu',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(244,58,'VE',NULL,'Venezuela',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(245,84,'VN',NULL,'Viet Nam',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(246,1284,'VG',NULL,'Virgin Islands, British',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(247,1340,'VI',NULL,'Virgin Islands, U.s.',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(248,681,'WF',NULL,'Wallis and Futuna',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(249,212,'EH',NULL,'Western Sahara',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(250,967,'YE',NULL,'Yemen',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(251,260,'ZM',NULL,'Zambia',0,'2022-09-29 03:02:32','2022-09-29 07:02:32'),(252,263,'ZW',NULL,'Zimbabwe',0,'2022-09-29 03:02:32','2022-09-29 07:02:32');
/*!40000 ALTER TABLE `countries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `distributor_clinic_prices`
--

DROP TABLE IF EXISTS `distributor_clinic_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `distributor_clinic_prices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `distributor_id` int DEFAULT NULL,
  `clinic_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `unit_price` double NOT NULL,
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_57C3D89B2D863A58` (`distributor_id`),
  KEY `IDX_57C3D89BCC22AD4` (`clinic_id`),
  KEY `IDX_57C3D89B4584665A` (`product_id`),
  CONSTRAINT `FK_57C3D89B2D863A58` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`),
  CONSTRAINT `FK_57C3D89B4584665A` FOREIGN KEY (`product_id`) REFERENCES `distributor_products` (`id`),
  CONSTRAINT `FK_57C3D89BCC22AD4` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `distributor_clinic_prices`
--

LOCK TABLES `distributor_clinic_prices` WRITE;
/*!40000 ALTER TABLE `distributor_clinic_prices` DISABLE KEYS */;
/*!40000 ALTER TABLE `distributor_clinic_prices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `distributor_clinics`
--

DROP TABLE IF EXISTS `distributor_clinics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `distributor_clinics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `distributor_id` int NOT NULL,
  `clinic_id` int NOT NULL,
  `is_active` int NOT NULL DEFAULT '0',
  `is_ignored` int NOT NULL DEFAULT '0',
  `client_id` varchar(255) DEFAULT NULL,
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_distributor_clinics_1_idx` (`distributor_id`),
  KEY `fk_distributor_clinics_2_idx` (`clinic_id`),
  CONSTRAINT `fk_distributor_clinics_1` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`),
  CONSTRAINT `fk_distributor_clinics_2` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `distributor_clinics`
--

LOCK TABLES `distributor_clinics` WRITE;
/*!40000 ALTER TABLE `distributor_clinics` DISABLE KEYS */;
/*!40000 ALTER TABLE `distributor_clinics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `distributor_products`
--

DROP TABLE IF EXISTS `distributor_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `distributor_products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `distributor_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `item_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `distributor_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` double NOT NULL,
  `stock_count` int NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `tax_exempt` tinyint(1) NOT NULL,
  `is_active` int NOT NULL DEFAULT '0',
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `distributor_id_fk_idx` (`distributor_id`),
  KEY `product_id_fk_idx` (`product_id`),
  KEY `tax_exempt` (`tax_exempt`),
  CONSTRAINT `distributor_products_ibfk_1` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`),
  CONSTRAINT `distributor_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `distributor_products`
--

LOCK TABLES `distributor_products` WRITE;
/*!40000 ALTER TABLE `distributor_products` DISABLE KEYS */;
/*!40000 ALTER TABLE `distributor_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `distributor_user_permissions`
--

DROP TABLE IF EXISTS `distributor_user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `distributor_user_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `distributor_id` int NOT NULL,
  `user_id` int NOT NULL,
  `permission_id` int NOT NULL,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `distributor_id_fk_idx` (`distributor_id`),
  KEY `permission_id_fk_idx` (`permission_id`),
  KEY `fk_distributor_user_permissions_1` (`user_id`),
  CONSTRAINT `fk_distributor_user_permissions_1` FOREIGN KEY (`user_id`) REFERENCES `distributor_users` (`id`),
  CONSTRAINT `permission_distributor_id_fk` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`),
  CONSTRAINT `permission_id_fkx` FOREIGN KEY (`permission_id`) REFERENCES `user_permissions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=887 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `distributor_user_permissions`
--

LOCK TABLES `distributor_user_permissions` WRITE;
/*!40000 ALTER TABLE `distributor_user_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `distributor_user_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `distributor_users`
--

DROP TABLE IF EXISTS `distributor_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `distributor_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `distributor_id` int DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hashed_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iso_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intl_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `roles` json NOT NULL,
  `reset_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint NOT NULL DEFAULT '0',
  `modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_4E5B79C72D863A58` (`distributor_id`),
  CONSTRAINT `FK_4E5B79C72D863A58` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `distributor_users`
--

LOCK TABLES `distributor_users` WRITE;
/*!40000 ALTER TABLE `distributor_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `distributor_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `distributors`
--

DROP TABLE IF EXISTS `distributors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `distributors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `distributor_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telephone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iso_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `intl_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hashed_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_postal_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_country_id` int DEFAULT NULL,
  `about` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `operating_hours` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `refund_policy` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sales_tax_policy` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `shipping_policy` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `po_number_prefix` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_manufaturer` tinyint(1) DEFAULT NULL,
  `theme_id` int DEFAULT NULL,
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `address_country_id_fk_idx` (`address_country_id`),
  CONSTRAINT `distributors_ibfk_1` FOREIGN KEY (`address_country_id`) REFERENCES `countries` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `distributors`
--

LOCK TABLES `distributors` WRITE;
/*!40000 ALTER TABLE `distributors` DISABLE KEYS */;
/*!40000 ALTER TABLE `distributors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctrine_migration_versions`
--

DROP TABLE IF EXISTS `doctrine_migration_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctrine_migration_versions`
--

LOCK TABLES `doctrine_migration_versions` WRITE;
/*!40000 ALTER TABLE `doctrine_migration_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `doctrine_migration_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_log`
--

DROP TABLE IF EXISTS `event_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `distributor_id` int DEFAULT NULL,
  `clinic_id` int DEFAULT NULL,
  `orders_id` int DEFAULT NULL,
  `status_id` int DEFAULT NULL,
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_9EF0AD162D863A58` (`distributor_id`),
  KEY `IDX_9EF0AD16CC22AD4` (`clinic_id`),
  KEY `IDX_9EF0AD16CFFE9AD6` (`orders_id`),
  KEY `IDX_9EF0AD166BF700BD` (`status_id`),
  CONSTRAINT `FK_9EF0AD162D863A58` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`),
  CONSTRAINT `FK_9EF0AD166BF700BD` FOREIGN KEY (`status_id`) REFERENCES `status` (`id`),
  CONSTRAINT `FK_9EF0AD16CC22AD4` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  CONSTRAINT `FK_9EF0AD16CFFE9AD6` FOREIGN KEY (`orders_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_log`
--

LOCK TABLES `event_log` WRITE;
/*!40000 ALTER TABLE `event_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `helper_country`
--

DROP TABLE IF EXISTS `helper_country`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `helper_country` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(3) NOT NULL,
  `name` varchar(150) NOT NULL,
  `dial_code` int NOT NULL,
  `currency_name` varchar(255) DEFAULT NULL,
  `currency_symbol` varchar(20) DEFAULT NULL,
  `currency_code` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=247 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `helper_country`
--

LOCK TABLES `helper_country` WRITE;
/*!40000 ALTER TABLE `helper_country` DISABLE KEYS */;
/*!40000 ALTER TABLE `helper_country` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `list_items`
--

DROP TABLE IF EXISTS `list_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `list_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `list_id` int NOT NULL,
  `product_id` int NOT NULL,
  `distributor_id` int DEFAULT NULL,
  `distributor_product_id` int NOT NULL,
  `item_id` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `qty` int NOT NULL,
  `modified` timestamp NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `list_id_fk_idx` (`list_id`),
  KEY `product_id_fk_idx` (`product_id`),
  KEY `distributor_id_fk_idx` (`distributor_id`),
  KEY `distributor_product_id_fk_idx` (`distributor_product_id`),
  CONSTRAINT `distributor_id_fk` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`),
  CONSTRAINT `list_id_fk` FOREIGN KEY (`list_id`) REFERENCES `lists` (`id`),
  CONSTRAINT `list_items_ibfk_1` FOREIGN KEY (`distributor_product_id`) REFERENCES `distributor_products` (`id`),
  CONSTRAINT `product_id_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=489 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `list_items`
--

LOCK TABLES `list_items` WRITE;
/*!40000 ALTER TABLE `list_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `list_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lists`
--

DROP TABLE IF EXISTS `lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lists` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinic_id` int DEFAULT NULL,
  `list_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_count` int NOT NULL,
  `is_protected` int NOT NULL DEFAULT '0',
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_8269FA5CC22AD4` (`clinic_id`),
  CONSTRAINT `FK_8269FA5CC22AD4` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lists`
--

LOCK TABLES `lists` WRITE;
/*!40000 ALTER TABLE `lists` DISABLE KEYS */;
/*!40000 ALTER TABLE `lists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `manufacturers`
--

DROP TABLE IF EXISTS `manufacturers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `manufacturers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(45) NOT NULL,
  `modified` timestamp NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `manufacturers`
--

LOCK TABLES `manufacturers` WRITE;
/*!40000 ALTER TABLE `manufacturers` DISABLE KEYS */;
INSERT INTO `manufacturers` VALUES (15,'Dechra','2022-10-26 07:22:04','2022-10-26 09:22:04');
/*!40000 ALTER TABLE `manufacturers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinic_id` int NOT NULL,
  `availability_tracker_id` int DEFAULT NULL,
  `orders_id` int DEFAULT NULL,
  `distributor_id` int DEFAULT NULL,
  `notification` text,
  `is_read` int NOT NULL DEFAULT '0',
  `is_active` int NOT NULL DEFAULT '1',
  `is_tracking` int NOT NULL DEFAULT '0',
  `is_order` int NOT NULL DEFAULT '0',
  `is_message` int NOT NULL DEFAULT '0',
  `modified` timestamp NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `modified_UNIQUE` (`modified`),
  KEY `orders_id_fk_idx` (`orders_id`),
  KEY `distributor_id_fk_idx` (`distributor_id`),
  KEY `is_tracking` (`is_tracking`),
  KEY `is_active` (`is_active`),
  KEY `is_read` (`is_read`),
  KEY `is_order` (`is_order`),
  KEY `is_message` (`is_message`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`),
  CONSTRAINT `orders_id_fk` FOREIGN KEY (`orders_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=391 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orders_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `distributor_id` int DEFAULT NULL,
  `item_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL,
  `quantity_delivered` int DEFAULT NULL,
  `unit_price` double NOT NULL,
  `total` double NOT NULL,
  `po_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `expiry_date` date DEFAULT NULL,
  `order_received_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_placed_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_accepted` int NOT NULL DEFAULT '0',
  `is_renegotiate` int NOT NULL DEFAULT '0',
  `is_cancelled` int NOT NULL DEFAULT '0',
  `is_confirmed_distributor` int NOT NULL DEFAULT '0',
  `is_quantity_correct` int NOT NULL DEFAULT '0',
  `is_quantity_incorrect` int NOT NULL DEFAULT '0',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reject_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_accepted_on_delivery` int NOT NULL DEFAULT '0',
  `is_rejected_on_delivery` int NOT NULL DEFAULT '0',
  `is_quantity_adjust` int NOT NULL DEFAULT '0',
  `modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_62809DB0CFFE9AD6` (`orders_id`),
  KEY `IDX_62809DB04584665A` (`product_id`),
  KEY `IDX_62809DB02D863A58` (`distributor_id`),
  KEY `po_number` (`po_number`),
  CONSTRAINT `FK_62809DB02D863A58` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`),
  CONSTRAINT `FK_62809DB04584665A` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `FK_62809DB0CFFE9AD6` FOREIGN KEY (`orders_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=538 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_status`
--

DROP TABLE IF EXISTS `order_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orders_id` int NOT NULL,
  `distributor_id` int NOT NULL,
  `status_id` int DEFAULT NULL,
  `po_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `orders_id_idx` (`orders_id`),
  KEY `distributor_id_fk_idx` (`distributor_id`),
  CONSTRAINT `order_status_ibfk_1` FOREIGN KEY (`orders_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `order_status_ibfk_2` FOREIGN KEY (`distributor_id`) REFERENCES `distributors` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=286 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_status`
--

LOCK TABLES `order_status` WRITE;
/*!40000 ALTER TABLE `order_status` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clinic_id` int DEFAULT NULL,
  `basket_id` int NOT NULL,
  `address_id` int DEFAULT NULL,
  `billing_address_id` int DEFAULT NULL,
  `delivery_fee` double DEFAULT NULL,
  `sub_total` double NOT NULL,
  `tax` double NOT NULL,
  `total` double NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_E52FFDEECC22AD4` (`clinic_id`),
  KEY `IDX_E52FFDEEF5B7AF75` (`address_id`),
  CONSTRAINT `FK_E52FFDEECC22AD4` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  CONSTRAINT `FK_E52FFDEEF5B7AF75` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pages`
--

DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pages`
--

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
INSERT INTO `pages` VALUES (1,'Home Page',NULL,'2022-10-10 08:41:45','2022-10-10 10:41:45'),(2,'Support','Advice And Answers From The Fluid Team\n','2022-10-18 05:54:56','2022-10-17 07:02:10'),(3,'Privacy','Privacy Policy','2022-10-18 05:54:56','2022-10-17 07:02:10'),(4,'Terms','Terms & Conditions','2022-10-18 05:54:56','2022-10-17 07:02:10'),(5,'About','About Fluid','2022-10-18 05:54:56','2022-10-18 07:54:56');
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_favourites`
--

DROP TABLE IF EXISTS `product_favourites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_favourites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `clinic_id` int NOT NULL,
  `modified` timestamp NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id_fk_idx` (`product_id`),
  KEY `clinic_id_fk_idx` (`clinic_id`),
  CONSTRAINT `product_favourites_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `product_favourites_ibfk_2` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_favourites`
--

LOCK TABLES `product_favourites` WRITE;
/*!40000 ALTER TABLE `product_favourites` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_favourites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_forms`
--

DROP TABLE IF EXISTS `product_forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_forms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_forms`
--

LOCK TABLES `product_forms` WRITE;
/*!40000 ALTER TABLE `product_forms` DISABLE KEYS */;
INSERT INTO `product_forms` VALUES (1,'Each','2022-09-05 08:49:43','2022-09-05 12:49:43'),(2,'Pair','2022-09-05 10:59:39','2022-09-05 12:49:43'),(3,'Tablet','2022-09-05 08:49:43','2022-09-05 12:49:43');
/*!40000 ALTER TABLE `product_forms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `image` varchar(255) NOT NULL,
  `is_default` int NOT NULL DEFAULT '0',
  `file_type` int NOT NULL DEFAULT '0',
  `modified` timestamp NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_manufacturers`
--

DROP TABLE IF EXISTS `product_manufacturers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_manufacturers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `products_id` int NOT NULL,
  `manufacturers_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id_fk_idx` (`products_id`),
  KEY `manufacturer_id_fk_idx` (`manufacturers_id`),
  CONSTRAINT `manufacturer_id_fk` FOREIGN KEY (`manufacturers_id`) REFERENCES `manufacturers` (`id`),
  CONSTRAINT `product_manufacturers_ibfk_1` FOREIGN KEY (`products_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=677 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_manufacturers`
--

LOCK TABLES `product_manufacturers` WRITE;
/*!40000 ALTER TABLE `product_manufacturers` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_manufacturers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_notes`
--

DROP TABLE IF EXISTS `product_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int DEFAULT NULL,
  `clinic_id` int DEFAULT NULL,
  `clinic_user_id` int DEFAULT NULL,
  `note` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_A047DDD04584665A` (`product_id`),
  KEY `IDX_A047DDD0CC22AD4` (`clinic_id`),
  KEY `IDX_A047DDD0FC0F246D` (`clinic_user_id`),
  CONSTRAINT `FK_A047DDD04584665A` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `FK_A047DDD0CC22AD4` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  CONSTRAINT `FK_A047DDD0FC0F246D` FOREIGN KEY (`clinic_user_id`) REFERENCES `clinic_users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=146 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_notes`
--

LOCK TABLES `product_notes` WRITE;
/*!40000 ALTER TABLE `product_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_review_comments`
--

DROP TABLE IF EXISTS `product_review_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_review_comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `review_id` int DEFAULT NULL,
  `clinic_id` int DEFAULT NULL,
  `clinic_user_id` int DEFAULT NULL,
  `comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `clinic_id` (`clinic_id`),
  KEY `clinic_user_id` (`clinic_user_id`),
  KEY `product_review_comments_ibfk_1_idx` (`review_id`),
  CONSTRAINT `product_review_comments_ibfk_1` FOREIGN KEY (`review_id`) REFERENCES `product_reviews` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `product_review_comments_ibfk_2` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  CONSTRAINT `product_review_comments_ibfk_3` FOREIGN KEY (`clinic_user_id`) REFERENCES `clinic_users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_review_comments`
--

LOCK TABLES `product_review_comments` WRITE;
/*!40000 ALTER TABLE `product_review_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_review_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_review_likes`
--

DROP TABLE IF EXISTS `product_review_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_review_likes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_review_id` int NOT NULL,
  `clinic_user_id` int NOT NULL,
  `modified` timestamp NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_review_idx` (`product_review_id`),
  KEY `clinic_user_idx` (`clinic_user_id`),
  CONSTRAINT `clinic_user_id_fk` FOREIGN KEY (`clinic_user_id`) REFERENCES `clinic_users` (`id`),
  CONSTRAINT `product_review_id_fk` FOREIGN KEY (`product_review_id`) REFERENCES `product_reviews` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=204 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_review_likes`
--

LOCK TABLES `product_review_likes` WRITE;
/*!40000 ALTER TABLE `product_review_likes` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_review_likes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_reviews`
--

DROP TABLE IF EXISTS `product_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int DEFAULT NULL,
  `clinic_user_id` int DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `clinic` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `likes` int DEFAULT NULL,
  `rating` int NOT NULL,
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_B8A9F0BF4584665A` (`product_id`),
  KEY `IDX_B8A9F0BFFC0F246D` (`clinic_user_id`),
  CONSTRAINT `FK_B8A9F0BF4584665A` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `FK_B8A9F0BFFC0F246D` FOREIGN KEY (`clinic_user_id`) REFERENCES `clinic_users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_reviews`
--

LOCK TABLES `product_reviews` WRITE;
/*!40000 ALTER TABLE `product_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int DEFAULT NULL,
  `category2_id` int DEFAULT NULL,
  `category3_id` int DEFAULT NULL,
  `sub_category_id` int DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active_ingredient` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dosage` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_price` float DEFAULT NULL,
  `price_from` float DEFAULT NULL,
  `stock_count` int DEFAULT NULL,
  `expiry_date_required` tinyint(1) NOT NULL DEFAULT '0',
  `tags` json DEFAULT NULL,
  `slug` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `expiry_date_required` (`expiry_date_required`),
  KEY `is_published` (`is_published`),
  KEY `category_id` (`category_id`),
  FULLTEXT KEY `search` (`name`,`active_ingredient`,`description`,`slug`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories1` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products_species`
--

DROP TABLE IF EXISTS `products_species`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products_species` (
  `id` int NOT NULL AUTO_INCREMENT,
  `products_id` int NOT NULL,
  `species_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_7E320D4F6C8A81A9` (`products_id`),
  KEY `IDX_7E320D4FB2A1D860` (`species_id`),
  CONSTRAINT `FK_7E320D4F6C8A81A9` FOREIGN KEY (`products_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `FK_7E320D4FB2A1D860` FOREIGN KEY (`species_id`) REFERENCES `species` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=484 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products_species`
--

LOCK TABLES `api` WRITE;
/*!40000 ALTER TABLE `api` DISABLE KEYS */;
INSERT INTO `api` VALUES (1,'Zoho Inventory','2022-09-21 10:13:38','2022-09-21 12:13:38'),(2,'Test','2022-10-24 09:18:45','2022-10-24 11:18:45');
/*!40000 ALTER TABLE `api` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `refresh_tokens`
--

DROP TABLE IF EXISTS `refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `refresh_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `api_id` int NOT NULL,
  `token` varchar(255) NOT NULL,
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `refresh_tokens_ibfk_2_idx` (`api_id`),
  CONSTRAINT `refresh_tokens_ibfk_1` FOREIGN KEY (`api_id`) REFERENCES `api_details` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `refresh_tokens`
--

LOCK TABLES `refresh_tokens` WRITE;
/*!40000 ALTER TABLE `refresh_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `refresh_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reset_password_request`
--

DROP TABLE IF EXISTS `reset_password_request`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reset_password_request` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `selector` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hashed_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `expires_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`id`),
  KEY `IDX_7CE748AA76ED395` (`user_id`),
  CONSTRAINT `FK_7CE748AA76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reset_password_request`
--

LOCK TABLES `reset_password_request` WRITE;
/*!40000 ALTER TABLE `reset_password_request` DISABLE KEYS */;
/*!40000 ALTER TABLE `reset_password_request` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `species`
--

DROP TABLE IF EXISTS `species`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `species` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `species`
--

LOCK TABLES `species` WRITE;
/*!40000 ALTER TABLE `species` DISABLE KEYS */;
INSERT INTO `species` VALUES (6,'Canine','2022-10-26 09:21:42','2022-10-26 09:21:42'),(7,'Feline','2022-10-26 09:21:47','2022-10-26 09:21:47');
/*!40000 ALTER TABLE `species` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `status`
--

DROP TABLE IF EXISTS `status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `status`
--

LOCK TABLES `status` WRITE;
/*!40000 ALTER TABLE `status` DISABLE KEYS */;
/*!40000 ALTER TABLE `status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sub_categories`
--

DROP TABLE IF EXISTS `sub_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sub_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int DEFAULT NULL,
  `sub_category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modified` datetime NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sub_categories`
--

LOCK TABLES `sub_categories` WRITE;
/*!40000 ALTER TABLE `sub_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `sub_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tags`
--

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(180) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hashed_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `roles` json NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modified` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_8D93D649E7927C74` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (1,'NP64fKInxiyx0_757QpJgLyuVmDIaeDE7IXWajxpoJvdSS6rM-mgVr6mgg','39ea0a399731634c149da00413641d84','[\"ROLE_API\", \"ROLE_BANNER\", \"ROLE_SUPPORT\", \"ROLE_ARTICLE\", \"ROLE_COUNTRY\", \"ROLE_DISTRIBUTOR\", \"ROLE_TAG\", \"ROLE_ACTIVE_INGREDIENT\", \"ROLE_MANUFACTURER\", \"ROLE_PRODUCT\", \"ROLE_SUB_CATEGORY\", \"ROLE_SPECIE\", \"ROLE_COMMUNICATION_METHOD\", \"ROLE_CLINIC\", \"ROLE_CATEGORY\", \"ROLE_USER\", \"ROLE_ADMIN\"]','$2y$13$tCS3ZdQQJNTo3V4fuJIwcO09Q6ajMcezhMUYsnyv07BTMSTtfsMZS','NP64fKInxiyx0_757QpJgJyuVmDIaeA','NP64fKInxiyx0_757QpJgJyuSXrSf-XG','2022-10-24 05:34:54','2022-02-24 15:01:32'),(2,'7e1m0IA9hjZ5r2DGU9RxgtAV_Ftogt5WCfr-xtHWC6Etyg','4f84d947a5dd336d7c50b7e7b191663e','[\"ROLE_API\", \"ROLE_TAG\", \"ROLE_ARTICLE\", \"ROLE_MANUFACTURER\", \"ROLE_COUNTRY\", \"ROLE_DISTRIBUTOR\", \"ROLE_BANNER\", \"ROLE_ACTIVE_INGREDIENT\", \"ROLE_USER\", \"ROLE_ADMIN\", \"ROLE_CATEGORY\", \"ROLE_CLINIC\", \"ROLE_COMMUNICATION_METHOD\", \"ROLE_PRODUCT\", \"ROLE_SPECIE\", \"ROLE_SUB_CATEGORY\"]','$2y$13$tCS3ZdQQJNTo3V4fuJIwcO09Q6ajMcezhMUYsnyv07BTMSTtfsMZS','7e1m0IA9hjZ5r2DGU9RxgvAV_Fto','7e1m0IA9hjZ5r2DGU9Rxgvke9kc','2022-10-24 05:35:11','2022-05-19 14:20:03'),(18,'UWlGh7yngtnqSxHejfEb9RBzSPuJYaIuSY6Drf9ToLCB','27a4f1660ecce4e3f35acc70b17fe054','[\"ROLE_API\", \"ROLE_SUB_CATEGORY\", \"ROLE_SPECIE\", \"ROLE_PRODUCT\", \"ROLE_MANUFACTURER\", \"ROLE_COMMUNICATION_METHOD\", \"ROLE_ADMIN\", \"ROLE_CLINIC\", \"ROLE_CATEGORY\", \"ROLE_COMMUNICATION_METHOD,ROLE_ADMIN,ROLE_USER\", \"ROLE_USER\"]','$2y$13$.No4gzyqG.0fuHZRNnj7WOJwALJwLnM6oWHWdeWKr412Z.h6aQFxK','UWlGh7yngtnqSxHejfEb9TBzSPuJYaI','UWlGh7yngtnqSxHejfEb9TBzV-GTd6cH','2022-10-24 05:35:19','2022-08-04 10:59:36');
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_permissions`
--

DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `permission` varchar(255) NOT NULL,
  `info` text NOT NULL,
  `is_clinic` int NOT NULL DEFAULT '0',
  `is_distributor` int NOT NULL,
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_permissions`
--

LOCK TABLES `user_permissions` WRITE;
/*!40000 ALTER TABLE `user_permissions` DISABLE KEYS */;
INSERT INTO `user_permissions` VALUES (1,'Manage Baskets','Users can add, update, and remove items from supplier carts.',1,0,'2022-06-28 09:02:10','2022-06-27 12:30:38'),(2,'Edit Shopping Lists','Users can view and analyze a clinic\'s purchase history and trends.',1,0,'2022-06-27 08:35:01','2022-06-27 12:35:01'),(3,'Place Orders','Users can place orders with any connected suppliers.',1,0,'2022-06-27 08:35:44','2022-06-27 12:35:44'),(4,'Receive Invoices','Users can mark packing slips and invoices as received.',1,0,'2022-06-27 08:36:17','2022-06-27 12:36:17'),(5,'Manage Orders','Users can mark packing slips and orders as received',1,1,'2022-06-30 09:07:58','2022-06-27 12:36:47'),(6,'User Management','Users can add and remove other users from this account, and change a user\'s permission levels.',1,1,'2022-06-30 03:24:32','2022-06-27 12:37:20'),(7,'View Analytics','Users can view and analyze a clinic\'s purchase history and trends.',1,0,'2022-06-27 08:37:57','2022-06-27 12:37:57'),(8,'View Previous Purchases','This permission allows you to view the purchase history of a clinic.',1,0,'2022-06-27 08:38:31','2022-06-27 12:38:31'),(9,'View Pricing & Stock','Users can view your clinic\'s pricing and availability on any item.',1,0,'2022-06-27 08:39:01','2022-06-27 12:39:01'),(10,'Company Information','Users can update company information',1,1,'2022-06-30 03:13:10','2022-06-28 13:04:30'),(12,'Manage Adresses','Users can add, edit and delete shipping and billing addresses.',1,0,'2022-06-28 09:07:56','2022-06-28 13:07:02'),(13,'Communication Methods','Users can add, edit and delete communication methods',1,0,'2022-06-28 09:07:49','2022-06-28 13:07:49'),(15,'About','Users can update about us',0,1,'2022-06-30 03:10:13','2022-06-30 07:10:13'),(16,'Operating Hours','User can update company operating hours.',0,1,'2022-06-30 03:11:10','2022-06-30 07:11:10'),(17,'Refund Policy','Users can update refund policy',0,1,'2022-06-30 03:11:50','2022-06-30 07:11:50'),(18,'Sales Tax Policy','Users can update sales tax policy',0,1,'2022-06-30 03:12:32','2022-06-30 07:12:32'),(19,'Shipping Policy','Users can update shipping policy.',0,1,'2022-06-30 03:24:25','2022-06-30 07:24:25'),(20,'Inventory','Users can update the inventory',0,1,'2022-06-30 03:25:28','2022-06-30 07:25:28');
/*!40000 ALTER TABLE `user_permissions` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2022-10-26  9:28:08
