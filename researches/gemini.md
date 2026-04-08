# **हिन्दू पर्व गणना और पंचांग विज्ञान: शास्त्रीय, सांप्रदायिक (स्वामीनारायण-BAPS) और कच्छ-गुजरात क्षेत्रीय विविधताओं का महाकाव्यात्मक शोध प्रतिवेदन**

## **1\. प्रस्तावना और हिन्दू कालगणना की ज्ञानमीमांसा (Epistemology of Hindu Timekeeping)**

हिन्दू पर्वों और अनुष्ठानों की गणना मानव इतिहास में खगोलीय अवलोकन, क्षेत्रीय सामाजिक-सांस्कृतिक परंपराओं और अत्यंत कठोर गणितीय नियमों के सबसे परिष्कृत एकीकरण का प्रतिनिधित्व करती है। पश्चिमी ग्रेगोरियन कैलेंडर (Gregorian calendar) के विपरीत, जो विशुद्ध रूप से एक सौर कैलेंडर है और जिसमें समय की गणना केवल पृथ्वी के सूर्य के चारों ओर परिक्रमा पर निर्भर करती है, पारंपरिक हिन्दू पंचांग (Panchanga) एक जटिल चन्द्र-सौर (Lunisolar) प्रणाली है 1। यह प्रणाली सूर्य की दैनिक गति (Solar cycle) को चंद्रमा के चरणों (Synodic phases) के साथ सामंजस्य स्थापित करती है, जिससे यह सुनिश्चित होता है कि कृषि चक्र, ऋतु परिवर्तन और धार्मिक अनुष्ठान अनंत काल तक एक दूसरे के साथ समन्वय में रहें 1।

प्रदान की गई आधारभूत डेटा संरचना—एक प्रोग्रामेटिक एरे (Array) जो विभिन्न हिन्दू त्योहारों को सूचीबद्ध करता है—इन अत्यधिक जटिल परंपराओं को मशीन-पठनीय (machine-readable) तर्क में संहिताबद्ध करने का एक महत्वपूर्ण ढांचागत प्रयास है। हालांकि, इस डेटा का एक कठोर खगोलीय और शास्त्रीय ऑडिट (Audit) यह स्पष्ट करता है कि 'कर्मकाल' (Karmakala \- अनुष्ठान करने का सटीक समय), सांप्रदायिक विविधताओं (विशेष रूप से बोचासनवासी अक्षर पुरुषोत्तम स्वामीनारायण संस्था \- BAPS), और गुजरात तथा कच्छ प्रायद्वीप के क्षेत्रीय लोक पर्वों के एल्गोरिदमिक समाधान (Algorithmic resolution) में कई गंभीर कमियां और भ्रांतियां मौजूद हैं।

यह विस्तृत शोध प्रतिवेदन पर्व गणना को नियंत्रित करने वाले खगोलीय नियमों का गहन विश्लेषण प्रदान करता है, प्रदान की गई डेटा संरचना में मौजूद भ्रामक सूचनाओं को ठीक करता है, और स्वामीनारायण संप्रदाय की सूक्ष्म परंपराओं तथा कच्छ, गुजरात की जीवंत लोक विरासत को शामिल करने के लिए इस संग्रह का व्यापक विस्तार करता है।

## **2\. पंचांग का खगोलीय और गणितीय मैट्रिक्स (Astronomical Matrix of the Panchanga)**

किसी भी हिन्दू त्योहार को एल्गोरिदमिक रूप से हल करने के लिए, केवल तिथि-मिलान (Date-matching) से आगे बढ़कर हिन्दू कालगणना के पांच स्तंभों (पंचांग) को समझना आवश्यक है: तिथि (लूनर डे), वार (सप्ताह का दिन), नक्षत्र (लूनर मेंशन), योग (सूर्य और चंद्र का कोणीय योग), और करण (आधी तिथि) 2।

### **2.1 तिथि का खगोलीय गणित (The Mathematics of the Tithi)**

एक तिथि को उस समय के रूप में परिभाषित किया जाता है जो चंद्रमा और सूर्य के बीच अनुदैर्ध्य कोण (Longitudinal angle) को ठीक ![][image1] तक बढ़ने में लगता है 4। चूँकि चंद्रमा की कक्षा अण्डाकार (Elliptical) होती है, इसलिए इसके वेग में निरंतर परिवर्तन होता रहता है, जिसके कारण एक तिथि की अवधि लगभग 19 घंटे से लेकर 26 घंटे के बीच बदलती रहती है 4।

इस खगोलीय भिन्नता का अर्थ यह है कि एक ही तिथि दो ग्रेगोरियन दिनों तक विस्तारित हो सकती है, या इसके विपरीत, दो तिथियां एक ही सौर दिन (Solar day) के भीतर आ सकती हैं। इसके परिणामस्वरूप 'क्षय तिथि' (Deleted Tithi) और 'वृद्धि तिथि' (Extra Tithi) की घटनाएं होती हैं 5। किसी भी सॉफ्टवेयर आर्किटेक्चर को इन बदलावों को संभालने के लिए प्रोग्राम किया जाना चाहिए, अन्यथा त्योहार की तारीख एक दिन आगे या पीछे खिसक सकती है 6।

### **2.2 अमान्त बनाम पूर्णिमान्त प्रणालियां (Amanta vs. Purnimanta Systems)**

अखिल भारतीय पर्व गणना में विचलन का एक महत्वपूर्ण बिंदु चंद्र मास (Lunar month) के समापन का सीमांकन है। भारतीय उपमहाद्वीप को मोटे तौर पर दो पंचांग क्षेत्राधिकारों में विभाजित किया गया है:

| प्रणाली (System) | मास का समापन (Month Ends On) | प्रथम पक्ष (First Fortnight) | क्षेत्रीय प्रधानता (Regional Dominance) |
| :---- | :---- | :---- | :---- |
| **पूर्णिमान्त (Purnimanta)** | पूर्णिमा (Full Moon) | कृष्ण पक्ष (Waning Moon) | उत्तर भारत (उत्तर प्रदेश, बिहार, राजस्थान, मध्य प्रदेश, पंजाब) 7 |
| **अमान्त (Amanta)** | अमावस्या (New Moon) | शुक्ल पक्ष (Waxing Moon) | पश्चिम और दक्षिण भारत (गुजरात, महाराष्ट्र, कर्नाटक, तमिलनाडु, आंध्र प्रदेश) 8 |

इस संरचनात्मक अंतर के कारण, अमान्त प्रणाली में किसी दिए गए महीने का कृष्ण पक्ष (Krishna Paksha), पूर्णिमान्त प्रणाली में उसके **अगले** महीने के कृष्ण पक्ष से मेल खाता है 7।

उदाहरण के लिए, भगवान कृष्ण का जन्म (कृष्ण जन्माष्टमी) कृष्ण पक्ष की आठवीं तिथि को होता है। गुजरात (अमान्त प्रणाली) में, इसे 'श्रावण वद 8' (Shravana Vad 8\) कहा जाता है 10। वहीं उत्तर भारत (पूर्णिमान्त प्रणाली) में, बिल्कुल उसी खगोलीय दिन को 'भाद्रपद कृष्ण 8' (Bhadrapada Krishna 8\) कहा जाता है 12। प्रदान किए गए एल्गोरिदमिक एरे (Array) में month\_amanta और month\_purnimanta कुंजियों (Keys) का उपयोग करके इसे पकड़ने का प्रयास किया गया है, जो संरचनात्मक रूप से सही है, लेकिन प्रोग्रामेटिक रिज़ॉल्यूशन में एक महीने की त्रुटि (off-by-one-month error) से बचने के लिए इसका निर्दोष निष्पादन आवश्यक है।

## **3\. कर्मकाल का एल्गोरिदमिक समाधान (Algorithmic Resolution of Karmakala)**

डिजिटल कैलेंडरों में हिन्दू त्योहारों की गलत गणना का सबसे लगातार कारण 'कर्मकाल' (Karmakala) को हल करने में विफलता है। कर्मकाल दिन की वह विशिष्ट समय खिड़की (Time window) है जिसके दौरान पीठासीन देवता की ऊर्जा को सबसे शक्तिशाली माना जाता है 14। पारंपरिक ग्रंथ जैसे *धर्मसिंधु* (Dharmasindhu) और *निर्णयसिंधु* (Nirnayasindhu) सख्त नियम निर्धारित करते हैं कि किसी त्योहार को मनाने के लिए तिथि को किस समय सक्रिय होना चाहिए 14। यद्यपि सामान्यतः सूर्योदय के समय सक्रिय तिथि (उदय तिथि) दिन पर राज करती है, लेकिन विशिष्ट त्योहार इस नियम को खारिज (override) कर देते हैं।

### **3.1 निशीथ काल: मध्यरात्रि का खगोलीय संयोग (Nishita Kaal: The Midnight Conjunction)**

कुछ देवताओं, विशेष रूप से वे जो प्रलय या गहन ब्रह्मांडीय जन्म से जुड़े हैं, की पूजा मध्यरात्रि में की जाती है। निशीथ काल (Nishita Kaal) रात का 8वां मुहूर्त होता है (स्थानीय सूर्यास्त और सूर्योदय के समय के आधार पर रात लगभग 12:00 बजे से 1:00 बजे तक) 15।

* **महाशिवरात्रि (Maha Shivaratri):** *धर्मसिंधु* के अनुसार महाशिवरात्रि का व्रत उस दिन किया जाना चाहिए जब चतुर्दशी तिथि निशीथ काल के साथ प्रतिच्छेद (intersect) करती हो 15। यदि चतुर्दशी तिथि दो लगातार रातों के निशीथ काल में व्याप्त हो, तो जटिल 'वृद्धि' और 'व्याप्ति' नियम यह निर्धारित करते हैं कि सटीक दिन कौन सा होगा 18।  
* **कृष्ण जन्माष्टमी (Krishna Janmashtami):** भगवान कृष्ण के जन्म के लिए यह अनिवार्य है कि अष्टमी तिथि निशीथ काल के दौरान सक्रिय हो 19। इसके अलावा, यह गणना सांप्रदायिक रेखाओं (Sectarian lines) द्वारा भारी रूप से विभाजित है। *स्मार्त* (Smarta) संप्रदाय के अनुयायी अष्टमी और निशीथ काल के सरल प्रतिच्छेदन को प्राथमिकता देते हैं। जबकि *वैष्णव* (Vaishnava) संप्रदाय (जिसमें ISKCON भी शामिल है) के नियम यह अनिवार्य करते हैं कि अष्टमी सूर्योदय के समय शुद्ध होनी चाहिए (सप्तमी के साथ मिश्रित नहीं) और अक्सर *रोहिणी नक्षत्र* (Rohini Nakshatra) की उपस्थिति की मांग करते हैं 19।

### **3.2 प्रदोष काल और स्थिर लग्न: सांध्यकालीन समृद्धि (Pradosha Kaal and Sthir Lagna)**

प्रदोष काल सूर्यास्त के तुरंत बाद की सांध्य अवधि (Twilight period) को संदर्भित करता है, जो लगभग 144 मिनट तक चलती है।

* **दीपावली / लक्ष्मी पूजा (Diwali / Lakshmi Puja):** धन और समृद्धि का आह्वान तब किया जाता है जब दिन की ऊर्जा रात में परिवर्तित होती है। लक्ष्मी पूजा के लिए आवश्यक है कि अमावस्या तिथि प्रदोष काल के दौरान सक्रिय हो 21। यह सुनिश्चित करने के लिए कि धन की देवी (लक्ष्मी) घर में स्थिर (Stationary) रहें, सटीक अनुष्ठान का समय एक 'स्थिर लग्न' (Fixed Ascendant) के साथ मेल खाने के लिए कड़ाई से निर्धारित किया जाता है—विशेष रूप से वृषभ (Taurus), सिंह (Leo), वृश्चिक (Scorpio), या कुंभ (Aquarius) 22। वृषभ लग्न (Vrishabha Lagna), जो आमतौर पर कार्तिक अमावस्या के दौरान प्रदोष काल के साथ ओवरलैप होता है, को लक्ष्मी पूजा के लिए सार्वभौमिक रूप से सबसे शुभ खिड़की (Auspicious window) माना जाता है 22।

### **3.3 मध्याह्न: सूर्य का चरम (Madhyahna: The Zenith of the Sun)**

मध्याह्न (Madhyahna) दिन के मध्य भाग का प्रतिनिधित्व करता है (मोटे तौर पर सुबह 11:00 बजे से दोपहर 1:00 बजे तक)।

* **राम नवमी (Rama Navami):** भगवान राम, जिनका जन्म सूर्यवंश (Solar Dynasty) में हुआ था, का अवतरण ठीक दोपहर में हुआ था। इसलिए, *धर्मसिंधु* यह निर्देशित करता है कि नवमी तिथि मध्याह्न अवधि के दौरान प्रबल होनी चाहिए 14। यदि नवमी सूर्योदय के समय सक्रिय है लेकिन मध्याह्न से पहले समाप्त हो जाती है, तो त्योहार को कैलेंडर के पिछले दिन (पिछले दिन के मध्याह्न में नवमी की उपस्थिति के कारण) वापस खींचा जा सकता है 14।

## **4\. स्वामीनारायण संप्रदाय (BAPS) के पर्वों का आलोचनात्मक ऑडिट और सुधार**

प्रदान किया गया PHP FESTIVALS एरे (Array) तिथि और पक्ष की एक मजबूत मूलभूत समझ को प्रदर्शित करता है, लेकिन इसमें महत्वपूर्ण सांप्रदायिक भ्रांतियां (Sectarian misinformation) शामिल हैं और इसमें बी.ए.पी.एस. (Bochasanwasi Akshar Purushottam Swaminarayan Sanstha \- BAPS) के विशिष्ट गुरुओं के उत्सवों की खगोलीय सटीकता का अभाव है। उपयोगकर्ता के डेटा में BAPS गुरुओं की जयंतियों से संबंधित कई प्रविष्टियां तथ्यात्मक रूप से गलत हैं।

### **4.1 पहचानी गई त्रुटियां और उनके खगोलीय सुधार (Identified Errors and Corrections)**

**त्रुटि 1: प्रमुख स्वामी महाराज जयंती (Pramukh Swami Maharaj Jayanti)**

* *उपयोगकर्ता का डेटा:* 'month\_amanta' \=\> 'Kartika', 'tithi' \=\> 10  
* *सटीक सुधार:* परम पावन प्रमुख स्वामी महाराज का जन्म *माघशीर्ष शुक्ल 8* (Magshar Sud 8 \- विक्रम संवत 1978\) को हुआ था 27। इसे 'कार्तिक शुक्ल 10' में निर्दिष्ट करना पूरी तरह से गलत है।

**त्रुटि 2: महंत स्वामी महाराज जयंती (Mahant Swami Maharaj Jayanti)**

* *उपयोगकर्ता का डेटा:* 'month\_amanta' \=\> 'Bhadrapada', 'tithi' \=\> 13  
* *सटीक सुधार:* परम पावन महंत स्वामी महाराज का भौतिक जन्म *भाद्रपद कृष्ण 9* (Bhadarva Vad 9 \- 13 सितंबर 1933\) को हुआ था 30।  
* *संस्थागत प्रतिमान बदलाव (Institutional Paradigm Shift):* BAPS संस्था ने हाल ही में एक बड़ा नीतिगत और आध्यात्मिक बदलाव किया है। यह आधिकारिक रूप से घोषित किया गया है कि महंत स्वामी महाराज की 92वीं जयंती से, उनका जन्मदिन अब उनके भौतिक जन्म तिथि पर नहीं मनाया जाएगा। इसके बजाय, संस्थागत रूप से उनकी जयंती उनके **पार्षदी दीक्षा दिन (Parshadi Diksha Din)** पर मनाई जाएगी 33। महंत स्वामी महाराज (तब विनु भगत) को 2 फरवरी 1957 को योगीजी महाराज द्वारा दीक्षा दी गई थी 31। हिन्दू पंचांग के अनुसार, यह ऐतिहासिक तिथि **महा वद एकम / प्रतिपदा** (Magha Krishna 1 \- Amanta) है 28। किसी भी सटीक डिजिटल कैलेंडर को संप्रदाय के इस आधुनिक विकास को प्रतिबिंबित करना चाहिए।

### **4.2 स्वामीनारायण (BAPS) कालक्रम का पुनर्गठन (Restructuring the BAPS Temporal Architecture)**

स्वामीनारायण संप्रदाय के संपूर्ण उत्सवों 36 के एल्गोरिदमिक समावेश को सुनिश्चित करने के लिए, निम्नलिखित विशिष्ट पर्वों को सही गणना तर्कों के साथ मैप किया जाना चाहिए:

| पर्व (Festival) | तिथि गणना (Tithi Calculation) | आध्यात्मिक महत्व (Spiritual Significance) | कर्मकाल और अनुष्ठान (Karmakala & Rituals) |
| :---- | :---- | :---- | :---- |
| **श्री हरि नवमी (स्वामीनारायण जयंती)** | चैत्र शुक्ल 9 (Chaitra Shukla 9\) | भगवान स्वामीनारायण का प्राकट्य दिवस (जन्म)। यह राम नवमी के साथ मेल खाता है 10। | राम की पूजा दोपहर में होती है, लेकिन BAPS भक्त पूरे दिन निर्जला उपवास (Waterless fast) रखते हैं। जन्म आरती ठीक रात 10:10 बजे की जाती है 38। |
| **शास्त्रीजी महाराज जयंती** | माघ शुक्ल 5 (Magha Shukla 5\) | BAPS के संस्थापक और अक्षर-पुरुषोत्तम दर्शन के प्रवर्तक, शास्त्रीजी महाराज का जन्म (1865) 39। | यह वसंत पंचमी (Vasant Panchami) और शिक्षापत्री जयंती (Shikshapatri Jayanti) के दिन ही आता है 10। |
| **प्रमुख वर्णी दिन (Pramukh Varni Din)** | ज्येष्ठ शुक्ल 4 (Jyeshtha Shukla 4\) | 1950 में 28 वर्षीय नारायणस्वरूपदास स्वामी (प्रमुख स्वामी) को BAPS का प्रशासनिक अध्यक्ष नियुक्त किया गया था 10। | संस्थागत प्रशासनिक और आध्यात्मिक स्मरणोत्सव। |
| **गुणातीतानंद स्वामी दीक्षा दिन** | पौष शुक्ल 15 (Pausha Shukla 15\) | मूल अक्षरब्रह्म गुणातीतानंद स्वामी को भगवान स्वामीनारायण द्वारा साधु-दीक्षा दी गई थी 28। | पोषी पूर्णिमा (Poshi Poornima) के अवसर पर। |
| **नीलकंठ वर्णी कल्याण यात्रा** | आषाढ़ शुक्ल 10 से श्रावण कृष्ण 6 | भगवान स्वामीनारायण (नीलकंठ वर्णी के रूप में) ने 1792 में वन-विचरण (12,000 किमी) शुरू किया और सात साल बाद लोज पहुंचे 42। | विशेष रूप से BAPS मंदिरों में नीलकंठ वर्णी की मूर्ति का अभिषेक किया जाता है 43। |
| **भगतजी महाराज जयंती** | फाल्गुन शुक्ल 15 (Phalguna Shukla 15\) | द्वितीय आध्यात्मिक उत्तराधिकारी का जन्म 10। | यह फाल्गुन पूर्णिमा / पुष्पदोलोत्सव (होली) के साथ मेल खाता है 10। |
| **योगीजी महाराज जयंती** | वैशाख कृष्ण 12 (Vaishakha Krishna 12\) | चतुर्थ आध्यात्मिक उत्तराधिकारी, योगीजी महाराज का जन्म 10। | ध्यान और कीर्तन आराधना। |

## **5\. क्षेत्रीय विशिष्टताएं: कच्छ और गुजरात की स्थानिक कालक्रम प्रणाली (Regional Specificities of Gujarat & Kutch)**

उपयोगकर्ता की क्वेरी (Query) स्पष्ट रूप से "भुज, गुजरात" (Bhuj, Gujarat) के वर्तमान स्थान का संदर्भ देती है। कच्छ प्रायद्वीप (Kutch Peninsula) गुजरात के भीतर एक विशिष्ट सांस्कृतिक और भौगोलिक सूक्ष्म जगत (Microcosm) के रूप में कार्य करता है। इसका अपना कृषक कैलेंडर, विशिष्ट लोक देवता और विशाल मौसमी मेले हैं जो पूरी तरह से मूल एरे (Base array) से गायब हैं। एक त्रुटिहीन रिपोर्ट के लिए इन स्थानीय विविधताओं का एकीकरण अत्यंत आवश्यक है।

### **5.1 आषाढ़ी बीज: कच्छी नव वर्ष (Ashadhi Bij: The Kutchi New Year)**

जबकि शेष गुजरात 'बेस्तु वरस' (Bestu Varas) के रूप में कार्तिक शुक्ल प्रतिपदा (दीपावली के अगले दिन) को अपना नव वर्ष मनाता है 47, कच्छी समुदाय अपना नव वर्ष **आषाढ़ी बीज** (Ashadhi Bij) को मनाता है, जो आषाढ़ माह के शुक्ल पक्ष की द्वितीया (Ashadha Shukla 2\) को पड़ता है 48।

यह त्योहार कच्छ की शुष्क भौगोलिक स्थिति और मानसून की शुरुआत से गहराई से जुड़ा हुआ है 50। आषाढ़ी बीज के दौरान, कच्छी किसान और विशेषज्ञ वायुमंडल में नमी, हवा की दिशा और वर्षा की भविष्यवाणी करने के लिए पारंपरिक मौसम विज्ञान का उपयोग करते हैं, जिससे यह तय होता है कि आगामी कृषि चक्र में कौन सी फसल सबसे उपयुक्त होगी 48। इसी दिन भगवान जगन्नाथ की रथ यात्रा (Rath Yatra) भी निकाली जाती है, जो इसे एक अत्यंत पवित्र क्षेत्रीय पर्व बनाता है 49।

### **5.2 श्रावण मास का गुजराती मैट्रिक्स (The Shravana Matrix in Gujarat)**

चूंकि गुजरात अमान्त कैलेंडर (Amanta calendar) का पालन करता है, यहाँ श्रावण मास उत्तर भारत की तुलना में लगभग 15 दिन बाद शुरू होता है 7। गुजरात में श्रावण का कृष्ण पक्ष (Vad) विशेष लोक व्रतों और पर्वों से सघन है, जो स्वास्थ्य, परिवार और कृषि धन के संरक्षण पर केंद्रित हैं:

* **बोल चौथ (Bol Choth / Bahula Chaturthi):** श्रावण कृष्ण 4 (Shravana Krishna 4)। इस दिन गायों और बछड़ों की पूजा की जाती है 54।  
* **गोगा पंचम / नाग पंचमी (Goga Pancham):** श्रावण कृष्ण 5 (Shravana Krishna 5)। जबकि शेष भारत श्रावण शुक्ल 5 को नाग पंचमी मनाता है, गुजरात इसे कृष्ण पक्ष में मनाता है, जहाँ गोगा महाराज (Goga Maharaj) जैसे क्षेत्रीय सर्प देवताओं को सम्मानित किया जाता है 54।  
* **रांधण छठ (Randhan Chhath):** श्रावण कृष्ण 6 (Shravana Krishna 6)। यह पूरा दिन केवल खाना पकाने के लिए समर्पित होता है। इसके बाद चूल्हे (Hearth) की पूजा की जाती है और उसे बुझा दिया जाता है 11। यह भगवान बलराम की जयंती (हल षष्ठी) के साथ भी मेल खाता है 54।  
* **शीतला सातम (Sheetala Satam):** श्रावण कृष्ण 7 (Shravana Krishna 7)। इस दिन घर में चूल्हा नहीं जलाया जाता। भक्त रांधण छठ के दिन तैयार किया गया ठंडा भोजन ही ग्रहण करते हैं और शीतला माता की पूजा करते हैं, ताकि परिवार को चेचक और बुखार जैसी बीमारियों से बचाया जा सके 11।

### **5.3 सौराष्ट्र और कच्छ के महान लोक मेले (The Great Folk Fairs of Saurashtra and Kutch)**

भुज के निवासी के लिए एक प्रासंगिक रिपोर्ट देने हेतु, विशाल लोक मेलों का समावेश अनिवार्य है। ये मेले कठोर तिथि संरेखण (Tithi alignments) का उपयोग करके गणना किए जाते हैं:

**1\. मोटा यक्ष मेला / जक्ख बोतेरा (Mota Yaksh Fair)** काकड़भिट (भुज से 40 किमी दूर) के पास आयोजित, यह कच्छ जिले का सबसे बड़ा मेला है 56। यह '72 जक्खों' (72 Jakhs) को सम्मानित करता है, जिन्हें घोड़े पर सवार गोरे रंग के देवदूत या विदेशी माना जाता है, जिन्होंने सदियों पहले एक क्रूर राजा, पुंव रा जाम (Punv Ra Jam) के अत्याचारों से कच्छ के लोगों को बचाया था 58। इस मेले की गणना एक आकर्षक क्षेत्रीय लचीलापन प्रदर्शित करती है: यह मुख्य रूप से भाद्रपद के *दूसरे सोमवार* (Second Monday of Bhadrapada) को चरम पर होता है, लेकिन इसका विस्तार **भाद्रपद कृष्ण 12, 13 और 14** (Bhadarva Vad 12-14) तिथियों तक रहता है 59।

**2\. रवेची माता का मेला (Ravechi Mata Fair)** कच्छ के रापर (Rapar) तालुका में प्राचीन रवेची मंदिर (किवदंतियों के अनुसार पांडवों द्वारा निर्मित) में आयोजित, यह मेला **भाद्रपद शुक्ल 8** (Bhadarva Sud Aatham) को होता है 61। यहाँ रबारी और अहीर समुदायों के हजारों भक्त देवी की पूजा करने और पारंपरिक हस्तशिल्प का व्यापार करने आते हैं 63।

**3\. दादा मेकन मेला / ध्रांग मेला (Dada Mekan Fair / Dhrang Mela)** कच्छ के रण के पास ध्रांग गाँव में आयोजित, यह मेला ठीक महाशिवरात्रि (**माघ कृष्ण 14**) के साथ मेल खाता है 64। यह संत मेकन दादा (1667–1730) का सम्मान करता है, जो कापड़ी संप्रदाय के एक हिंदू संत थे। उन्होंने अपना जीवन रण के वीरान नमक रेगिस्तान में खोए हुए यात्रियों को बचाने में बिताया, जिसमें उनकी मदद उनका कुत्ता (मोतियो) और गधा (लालियो) करते थे 56।

**4\. तरणेतर मेला (Tarnetar Fair)** सौराष्ट्र के सुरेंद्रनगर जिले में आयोजित, यह गुजरात का सबसे प्रसिद्ध विवाह-संपर्क (Matchmaking) मेला है 67। महाभारत में द्रौपदी के 'स्वयंवर' की किंवदंती पर आधारित, यह **भाद्रपद शुक्ल 4 से 6** (Bhadarva Sud 4-6) तक त्रिनेत्रेश्वर महादेव मंदिर में आयोजित होता है 67। भरवाड़, रबारी और कोली जनजातियों के युवा बड़े रंग-बिरंगे कशीदाकारी वाले छाते लेकर आते हैं और 'रास' (Raas) तथा 'रासड़ा' (Rahado) जैसे पारंपरिक लोक नृत्य करते हुए अपने जीवनसाथी की तलाश करते हैं 67।

### **5.4 वासी उत्तरायण की खगोलीय वास्तविकता (Vasi Uttarayan)**

मूल एल्गोरिदमिक एरे 'वासी उत्तरायण' को एक निश्चित सौर तिथि (15 जनवरी) के रूप में सही ढंग से वर्गीकृत करता है। 14 जनवरी को मकर राशि में सूर्य का गोचर (मकर संक्रांति) 'उत्तरायण' (सूर्य की उत्तर दिशा की ओर गति) को चिह्नित करता है 70। गुजरात में, पतंगबाजी का उत्साह इतना तीव्र होता है कि राज्य आधिकारिक तौर पर इस त्योहार को दूसरे दिन तक बढ़ा देता है, जिसे 'वासी' (Vasi \- जिसका अर्थ है बासी या निरंतरता) उत्तरायण कहा जाता है, जो इसे एक अद्वितीय क्षेत्रीय सौर उत्सव बनाता है 72।

## **6\. दक्षिण भारतीय सौर-चन्द्र विसंगतियां (Dravidian Solar-Lunar Syncretism and Anomalies)**

जबकि उत्तर और पश्चिम भारत मुख्य रूप से चन्द्र-सौर (Lunisolar) मीट्रिक पर निर्भर हैं, दक्षिणी राज्य (जैसे तमिलनाडु, केरल) सख्त सौर कैलेंडरों (तमिल और मलयालम कैलेंडर) का उपयोग करते हैं 1। एक व्यापक पैन-इंडियन (Pan-Indian) पंचांग एल्गोरिदम को इन सौर-चन्द्र विसंगतियों को सटीक रूप से संभालना चाहिए।

### **6.1 वैकुण्ठ एकादशी की गणना का रहस्य (Vaikuntha Ekadashi Anomaly)**

उपयोगकर्ता का एरे वैकुण्ठ एकादशी को 'मार्गशीर्ष शुक्ल 11' पर रखता है। हालांकि यह चंद्र ढांचे में नाममात्र रूप से सही है, लेकिन दक्षिण भारतीय मंदिर परंपराओं (जैसे श्रीरंगम और तिरुपति) के लिए यह खगोलीय रूप से अपर्याप्त है 74।

वैकुण्ठ एकादशी को कड़ाई से उस शुक्ल पक्ष एकादशी के रूप में परिभाषित किया गया है जो **धनु सौर मास (Dhanurmasa)** के *भीतर* आती है (जो मध्य-दिसंबर से मध्य-जनवरी तक चलता है) 74। चूँकि चंद्र मास 29.5 दिनों का होता है और सौर मास 30.4 दिनों का होता है, इसलिए यह संरेखण प्रतिवर्ष बदलता रहता है 75। इसके परिणामस्वरूप:

* वैकुण्ठ एकादशी मार्गशीर्ष या पौष चंद्र मास में आ सकती है 76।  
* कभी-कभी, एक ही धनुर्मास के भीतर दो शुक्ल एकादशियां आ सकती हैं।  
* दुर्लभ ग्रेगोरियन वर्षों में, यह बिल्कुल नहीं आ सकती है, और पूरी तरह से आसन्न (adjacent) वर्ष में धकेल दी जाती है 75। इसलिए, एक एल्गोरिदम को इस विशिष्ट त्योहार के लिए चंद्र चरण की गणना करने से पहले सूर्य के धनु राशि (Sagittarius) में गोचर (Transit) को प्रोग्रामेटिक रूप से क्वेरी (Query) करना चाहिए।

### **6.2 स्कंद षष्ठी और सूरसंहारम (Skanda Sashti and Soorasamharam)**

भगवान मुरुगन (कार्तिकेय) को समर्पित, स्कंद षष्ठी तमिल महीने 'इप्पासी' (Aippasi \- जो चंद्र कार्तिक महीने से मेल खाता है) में शुक्ल पक्ष के छठे दिन (षष्ठी) को मनाई जाती है 78। यहाँ महत्वपूर्ण गणना नियम पंचमी (5वें दिन) और षष्ठी के संयोजन (Conjunction) से संबंधित है। *धर्मसिंधु* के अनुसार, यदि पंचमी के दिन सूर्योदय और सूर्यास्त के बीच षष्ठी तिथि शुरू हो जाती है, तो सूरसंहारम (6-दिवसीय उपवास अवधि का चरमोत्कर्ष) शुद्ध षष्ठी के दिन के बजाय उसी संयुग्मित दिन (Conjugated day) पर मनाया जाता है 79।

### **6.3 विशुद्ध सौर पर्व: पोंगल और ओणम (Pure Solar Observances: Pongal and Onam)**

* **थाई पोंगल (Thai Pongal):** विशुद्ध रूप से सौर गोचर पर गणना की जाती है। यह तमिल सौर महीने 'थाई' (Thai) का पहला दिन है (जब सूर्य मकर राशि में प्रवेश करता है) 80। यह चंद्र चरणों से पूर्णतः स्वतंत्र है।  
* **ओणम (Onam):** एक हाइब्रिड सौर-नक्षत्र (Solar-stellar) मीट्रिक का उपयोग करके गणना की जाती है। यह मलयालम सौर महीने 'चिंगम' (Chingam) में उस दिन मनाया जाता है जब **तिरुवोनम** (Thiruvonam / Shravana) नक्षत्र सक्रिय होता है 82। क्योंकि यह एक सौर महीने के भीतर एक विशिष्ट नक्षत्र पर निर्भर करता है, इसकी ग्रेगोरियन तारीख (Gregorian date) चंद्र त्योहारों की तुलना में काफी उतार-चढ़ाव दिखाती है 85।

## **7\. प्रस्तावित एल्गोरिदमिक डेटा संरचना (Proposed Algorithmic Data Structures)**

उपरोक्त शोध और खगोलीय विश्लेषण के आधार पर, प्रदान किए गए PHP एरे का विस्तार किया जाना चाहिए ताकि उसमें सटीक कर्मकाल (Karmakala) आवश्यकताएं, सही सांप्रदायिक तिथियां (Corrected sectarian dates) और क्षेत्रीय भू-स्थानिक झंडे (Geolocation flags) शामिल हों। नीचे कठोरता से सही और विस्तारित डेटा संरचना दी गई है जो वास्तविक विशेषज्ञ-स्तरीय (Expert-level) इंडोलॉजिकल लॉजिक (Indological logic) को दर्शाती है:

PHP

/\*\*  
 \* Exhaustive Hindu & Regional Festival Array  
 \* Rectified for Swaminarayan (BAPS), Kutch Regional, and Karmakala accuracy.  
 \*/  
public const FESTIVALS \=,  
    'Pramukh Varni Din' \=\>,  
    'Pramukh Swami Maharaj Jayanti' \=\>,  
    'Mahant Swami Maharaj Parshadi Diksha Din (Institutional Jayanti)' \=\>,  
    'Gunatitanand Swami Diksha Day' \=\>,

    // \--- Kutch & Gujarat Regional Endemics \---  
    'Ashadhi Bij (Kutchi New Year)' \=\>,  
    'Randhan Chhath' \=\>,  
    'Tarnetar Fair' \=\>,  
    'Ravechi Mata Fair' \=\>,  
    'Mota Yaksh Fair (Jakh Bahotera)' \=\>,  
    'Dada Mekan Fair (Dhrang Mela)' \=\>,  
        'karmakala\_type' \=\> 'nishita',  
    \],

    // \--- Complex Algorithmic Corrections \---  
    'Vaikuntha Ekadashi' \=\>,  
    'Diwali (Lakshmi Puja)' \=\> \[  
        'type' \=\> 'tithi',  
        'resolver' \=\> 'classical',  
        'paksha' \=\> 'Krishna',  
        'tithi' \=\> 15,  
        'month\_amanta' \=\> 'Ashvina',  
        'month\_purnimanta' \=\> 'Kartika',  
        'description' \=\> 'Festival of lights',  
        'deity' \=\> 'Lakshmi/Ganesha',  
        'karmakala\_type' \=\> 'pradosha\_sthir\_lagna', // Highly specific temporal requirement  
        'vriddhi\_preference' \=\> 'first',  
    \];

## **8\. निष्कर्ष और प्रणालीगत निहितार्थ (Strategic Conclusions and System Implications)**

हिन्दू कैलेंडर का डिजिटलीकरण केवल तिथियों के स्थिर मानचित्रण (Static mapping) पर निर्भर नहीं रह सकता। जैसा कि इस शोध प्रतिवेदन में स्पष्ट किया गया है, एक सटीक सॉफ्टवेयर एल्गोरिदम को कक्षीय यांत्रिकी (Orbital mechanics / Tithi angles), बदलते लौकिक प्रतिच्छेदनों (Karmakala), और गहन भौगोलिक तथा सांप्रदायिक विभाजनों का ध्यान रखना चाहिए।

पहली महत्वपूर्ण बात यह है कि सांप्रदायिक भ्रांतियों का निवारण अति-आवश्यक है। आधार एरे (Base array) में स्वामीनारायण (BAPS) वंशावली के बारे में गंभीर त्रुटियां थीं। प्रमुख स्वामी महाराज की जयंती मार्गशीर्ष में आती है, कार्तिक में नहीं 28। इसके अलावा, सॉफ्टवेयर प्रणालियों को जीवित और विकसित होती परंपराओं के अनुकूल होना चाहिए: महंत स्वामी महाराज के भौतिक जन्मदिवस के बजाय उनके 'पार्षदी दीक्षा दिन' (महा वद प्रतिपदा) पर उनकी जयंती मनाने का BAPS का हालिया जनादेश यह दर्शाता है कि धार्मिक कैलेंडर स्थिर नहीं हैं, बल्कि गतिशील हैं 28। एक मजबूत प्रोग्रामेटिक आर्किटेक्चर को इस तरह के संस्थागत प्रतिमान बदलावों (Paradigm shifts) को समायोजित करने में सक्षम होना चाहिए।

दूसरी बात, भू-स्थानिक संदर्भ (Geospatial Contextualization) की अनदेखी नहीं की जा सकती। भुज, गुजरात से क्वेरी करने वाला उपयोगकर्ता एक अद्वितीय सांस्कृतिक सातत्य (Cultural continuum) में मौजूद है। एक सामान्य अखिल भारतीय (Pan-Indian) कैलेंडर ऐसे उपयोगकर्ता के लिए अपर्याप्त है, क्योंकि यह आषाढ़ी बीज (कच्छी नव वर्ष) को छोड़ देता है, जो स्थानीय कृषि चक्रों और मानसून की भविष्यवाणियों को निर्देशित करता है 48। इसी तरह, मोटा यक्ष (Mota Yaksh) 56, रवेची (Ravechi) 61, तरणेतर (Tarnetar) 68, और दादा मेकन (Dada Mekan) 66 मेलों के व्यापक सामाजिक-आर्थिक और धार्मिक प्रभाव को नजरअंदाज करना एक स्थानीय कैलेंडर को पूरी तरह से अप्रासंगिक बना देता है।

अंततः, 'सौर अधिभावी' (Solar Override) का महत्व सर्वोपरि है। विशुद्ध रूप से चंद्र तर्क (Lunar logic) दक्षिण भारत और कुछ विशिष्ट त्यौहारों में विफल रहता है। एल्गोरिदम को वैकुण्ठ एकादशी (धनुर्मास) 74, ओणम (चिंगम और तिरुवोनम नक्षत्र) 82, और पोंगल (थाई) 80 जैसे पर्वों को सटीक रूप से रखने के लिए सौर गोचर (Solar transits) की क्वेरी करने के लिए सुसज्जित किया जाना चाहिए।

संशोधित डेटा संरचनाओं को लागू करके और कच्छ तथा गुजरात के स्थानीय लोक इतिहास के साथ-साथ *निर्णयसिंधु* की गणितीय कठोरता को स्वीकार करके, एक ऐसी क्रोनोमेट्रिक रूपरेखा (Chronometric framework) तैयार की जा सकती है जो न केवल गणितीय रूप से दोषरहित है, बल्कि सांस्कृतिक और सांप्रदायिक रूप से भी अत्यंत प्रामाणिक है।

#### **Works cited**

1. Hindu calendar \- Wikipedia, accessed April 8, 2026, [https://en.wikipedia.org/wiki/Hindu\_calendar](https://en.wikipedia.org/wiki/Hindu_calendar)  
2. Drik Panchang \- online Hindu Almanac and Calendar with Planetary Ephemeris based on Vedic Astrology., accessed April 8, 2026, [https://www.drikpanchang.com/](https://www.drikpanchang.com/)  
3. Month Panchang for Bhuj, Gujarat, India, accessed April 8, 2026, [https://www.drikpanchang.com/panchang/month-panchang.html?geoname-id=1275812](https://www.drikpanchang.com/panchang/month-panchang.html?geoname-id=1275812)  
4. Hindu Lunar Tithi Calendar | Panchang Thithi Calculator \- Astroica.com, accessed April 8, 2026, [https://www.astroica.com/vedic-astrology/tithi-calculator.php](https://www.astroica.com/vedic-astrology/tithi-calculator.php)  
5. January 2026 \- BAPS, accessed April 8, 2026, [https://www.baps.org/Calendar/2026/January.aspx](https://www.baps.org/Calendar/2026/January.aspx)  
6. Date of Hindu Festival shifts opposite to time zone shift \- Drik Panchang, accessed April 8, 2026, [https://www.drikpanchang.com/festivals/hindu-festival-date-shift-with-timezone.html](https://www.drikpanchang.com/festivals/hindu-festival-date-shift-with-timezone.html)  
7. Amanta vs Purnimanta: The Two Hindu Lunar Calendar Systems Explained \- Muhuratam, accessed April 8, 2026, [https://muhuratam.in/blog/amanta-vs-purnimanta](https://muhuratam.in/blog/amanta-vs-purnimanta)  
8. Difference between North Indian and South Indian Lunar Calendar \- Purnimanta and Amanta School \- Drik Panchang, accessed April 8, 2026, [https://www.drikpanchang.com/faq/faq-ans8.html](https://www.drikpanchang.com/faq/faq-ans8.html)  
9. Purnimanta vs. Amanta: Why Month Dates Differ Across India, accessed April 8, 2026, [https://monthnameshindi.com/purnimanta-vs-amanta-indian-calendar-difference/](https://monthnameshindi.com/purnimanta-vs-amanta-indian-calendar-difference/)  
10. FestivalList 2022 \- BAPS, accessed April 8, 2026, [https://www.baps.org/Calendar/2022/FestivalList.aspx](https://www.baps.org/Calendar/2022/FestivalList.aspx)  
11. Shitala Satam \- shrimadbhagvatam.org, accessed April 8, 2026, [https://shrimadbhagvatam.org/pinned\_posts/shitala-satam/](https://shrimadbhagvatam.org/pinned_posts/shitala-satam/)  
12. Hindu Festivals Collection \- Drik Panchang, accessed April 8, 2026, [https://www.drikpanchang.com/festivals/hindu-festivals.html](https://www.drikpanchang.com/festivals/hindu-festivals.html)  
13. Amanta calendar : r/IndicKnowledgeSystems \- Reddit, accessed April 8, 2026, [https://www.reddit.com/r/IndicKnowledgeSystems/comments/1qc1wit/amanta\_calendar/](https://www.reddit.com/r/IndicKnowledgeSystems/comments/1qc1wit/amanta_calendar/)  
14. Next:Kaala-Maasa-Paksha-Tithi Nirnaya \- Kamakoti.org, accessed April 8, 2026, [https://www.kamakoti.org/kamakoti/dharmasindhu/bookview.php?chapnum=5](https://www.kamakoti.org/kamakoti/dharmasindhu/bookview.php?chapnum=5)  
15. Midnight Mahurat Nishita Kaal when Universe Bows to Shiv Shakti : r/Nakshatras \- Reddit, accessed April 8, 2026, [https://www.reddit.com/r/Nakshatras/comments/1r4fgdu/midnight\_mahurat\_nishita\_kaal\_when\_universe\_bows/](https://www.reddit.com/r/Nakshatras/comments/1r4fgdu/midnight_mahurat_nishita_kaal_when_universe_bows/)  
16. Correct method to perform Mahashivaratri Vrata. How to celebrate Mahas \- Ushijo.co, accessed April 8, 2026, [https://www.ushijo.com/blogs/blogs-on-sanatana-dharma/correct-method-to-perform-mahashivaratri-vrata-how-to-celebrate-mahashivaratri](https://www.ushijo.com/blogs/blogs-on-sanatana-dharma/correct-method-to-perform-mahashivaratri-vrata-how-to-celebrate-mahashivaratri)  
17. Maha Shivratri for Beginners: Nishita Kaal, Vrat While Working & Simple Worship Guide, accessed April 8, 2026, [https://www.chaardham.in/blog/maha-shivratri-for-beginners-simple-worship-guide](https://www.chaardham.in/blog/maha-shivratri-for-beginners-simple-worship-guide)  
18. Mahashivratri 2026: February 15 or 16? Check Correct Date, Nishita Kaal & Parana Time, accessed April 8, 2026, [https://sundayguardianlive.com/india/mahashivratri-2026-february-15-or-16-check-correct-date-nishita-kaal-parana-time-170302/](https://sundayguardianlive.com/india/mahashivratri-2026-february-15-or-16-check-correct-date-nishita-kaal-parana-time-170302/)  
19. 2026 Krishna Janmashtami date for New Delhi, NCT, India \- Drik Panchang, accessed April 8, 2026, [https://www.drikpanchang.com/dashavatara/lord-krishna/krishna-janmashtami-date-time.html](https://www.drikpanchang.com/dashavatara/lord-krishna/krishna-janmashtami-date-time.html)  
20. Why is Janmashtami always celebrated in the evening? \- Hinduism Stack Exchange, accessed April 8, 2026, [https://hinduism.stackexchange.com/questions/36083/why-is-janmashtami-always-celebrated-in-the-evening](https://hinduism.stackexchange.com/questions/36083/why-is-janmashtami-always-celebrated-in-the-evening)  
21. Divali Puja Vidhi \- Shree Bharatiya Mandal, accessed April 8, 2026, [https://sbm.org.uk/?page\_id=1103](https://sbm.org.uk/?page_id=1103)  
22. Diwali & Lakshmi Puja Muhurta and Process \- The Times of India, accessed April 8, 2026, [https://timesofindia.indiatimes.com/religion/rituals-puja/diwali-lakshmi-puja-muhurta-and-process/articleshow/68205932.cms](https://timesofindia.indiatimes.com/religion/rituals-puja/diwali-lakshmi-puja-muhurta-and-process/articleshow/68205932.cms)  
23. The Deepawali Muhurta \- Howisyourdaytoday.com, accessed April 8, 2026, [https://howisyourdaytoday.com/Muhurta/Deepawali-Muhurta-for-Bulandshahr.htm](https://howisyourdaytoday.com/Muhurta/Deepawali-Muhurta-for-Bulandshahr.htm)  
24. Diwali 2025: Laxmi Puja rituals, muhurat, Complete 5-day Puja calendar — date & auspicious time \- The Economic Times, accessed April 8, 2026, [https://m.economictimes.com/news/international/us/diwali-2025-puja-calendar-muhurat-timing-laxmi-puja-rituals-5-day-puja-govardhan-puja-bhai-dooj-date-time/articleshow/124607884.cms](https://m.economictimes.com/news/international/us/diwali-2025-puja-calendar-muhurat-timing-laxmi-puja-rituals-5-day-puja-govardhan-puja-bhai-dooj-date-time/articleshow/124607884.cms)  
25. Ram Navami 2026 date: When is Ram Navami? Check timing, shubh muhurat, puja Vidhi, mantras, do's and don'ts \- The Economic Times, accessed April 8, 2026, [https://m.economictimes.com/news/new-updates/ram-navami-2026-date-when-is-ram-navami-check-timing-shubh-muhurat-puja-vidhi-mantras-dos-and-donts/articleshow/129768829.cms](https://m.economictimes.com/news/new-updates/ram-navami-2026-date-when-is-ram-navami-check-timing-shubh-muhurat-puja-vidhi-mantras-dos-and-donts/articleshow/129768829.cms)  
26. 2021 Rama Navami \- Arsha Vidya Gurukulam, accessed April 8, 2026, [https://arshavidya.org/wp-content/uploads/2021-Ramanavami-Puja-Vidhi.pdf](https://arshavidya.org/wp-content/uploads/2021-Ramanavami-Puja-Vidhi.pdf)  
27. Life \- HH Pramukh Swami Maharaj, accessed April 8, 2026, [https://pramukhswami.org/life/](https://pramukhswami.org/life/)  
28. FestivalList 2026 \- BAPS, accessed April 8, 2026, [https://www.baps.org/Calendar/2026/FestivalList.aspx](https://www.baps.org/Calendar/2026/FestivalList.aspx)  
29. FestivalList 2025 \- BAPS, accessed April 8, 2026, [https://www.baps.org/Calendar/2025/FestivalList.aspx](https://www.baps.org/Calendar/2025/FestivalList.aspx)  
30. Mahant Swami Maharaj \- Wikipedia, accessed April 8, 2026, [https://en.wikipedia.org/wiki/Mahant\_Swami\_Maharaj](https://en.wikipedia.org/wiki/Mahant_Swami_Maharaj)  
31. Mahant Swami Maharaj \- BAPS, accessed April 8, 2026, [https://www.baps.org/About-BAPS/TheFounder%E2%80%93BhagwanSwaminarayan/TheSpiritualLineage-TheGuruParampara/Mahant-Swami-Maharaj.aspx](https://www.baps.org/About-BAPS/TheFounder%E2%80%93BhagwanSwaminarayan/TheSpiritualLineage-TheGuruParampara/Mahant-Swami-Maharaj.aspx)  
32. A Brief History \- BAPS, accessed April 8, 2026, [https://www.baps.org/About-BAPS/TheFounder%E2%80%93BhagwanSwaminarayan/TheSpiritualLineage-TheGuruParampara/Mahant-Swami-Maharaj/A-Brief-History.aspx](https://www.baps.org/About-BAPS/TheFounder%E2%80%93BhagwanSwaminarayan/TheSpiritualLineage-TheGuruParampara/Mahant-Swami-Maharaj/A-Brief-History.aspx)  
33. HH Mahant Swami Maharaj Janma Jayanti \- BAPS, accessed April 8, 2026, [https://www.baps.org/Announcement/2025/HH-Mahant-Swami-Maharaj-Janma-Jayanti-28853.aspx](https://www.baps.org/Announcement/2025/HH-Mahant-Swami-Maharaj-Janma-Jayanti-28853.aspx)  
34. February 02, 1957 ISKCON Daily Panchang for New Delhi, NCT, India, accessed April 8, 2026, [https://www.drikpanchang.com/iskcon/panchang/iskcon-detailed-day-panchang.html?date=02/02/1957](https://www.drikpanchang.com/iskcon/panchang/iskcon-detailed-day-panchang.html?date=02/02/1957)  
35. February 02, 1957 Panchang, Panchanga, Panchangam for New Delhi, NCT, India, accessed April 8, 2026, [https://www.drikpanchang.com/panchang/day-panchang.html?date=02/02/1957](https://www.drikpanchang.com/panchang/day-panchang.html?date=02/02/1957)  
36. BAPS Swaminarayan Sanstha \- Home, accessed April 8, 2026, [https://www.baps.org/home.aspx](https://www.baps.org/home.aspx)  
37. Swaminarayan \- Wikipedia, accessed April 8, 2026, [https://en.wikipedia.org/wiki/Swaminarayan](https://en.wikipedia.org/wiki/Swaminarayan)  
38. SWAMINARAYAN JAYANTI: Hindus fast in modern tradition \- Religious Holidays, accessed April 8, 2026, [https://readthespirit.com/religious-holidays-festivals/swaminarayan-jayanti-hindu/](https://readthespirit.com/religious-holidays-festivals/swaminarayan-jayanti-hindu/)  
39. Shastriji Maharaj \- Wikipedia, accessed April 8, 2026, [https://en.wikipedia.org/wiki/Shastriji\_Maharaj](https://en.wikipedia.org/wiki/Shastriji_Maharaj)  
40. Shastriji Maharaj Jayanti Festival | Significance & Importance \- Shubh Panchang, accessed April 8, 2026, [https://shubhpanchang.com/festivals/shastriji-maharaj-jayanti](https://shubhpanchang.com/festivals/shastriji-maharaj-jayanti)  
41. Festivals \- Pramukh Varni Din \- || B A P S Swaminarayan Sanstha ||, accessed April 8, 2026, [http://www.swaminarayan.org/festivals/pramukhvarni/index.htm](http://www.swaminarayan.org/festivals/pramukhvarni/index.htm)  
42. Shree Nilkanth Varni, accessed April 8, 2026, [https://www.swaminarayangadi.com/news/2010/shree-nilkanth-varni](https://www.swaminarayangadi.com/news/2010/shree-nilkanth-varni)  
43. Swaminarayan Jayanti 2025 Festival: Significance, Rituals, and Modern Celebrations, accessed April 8, 2026, [https://www.dkscore.com/jyotishmedium/festival/swaminarayan-jayanti-2025-2025-178-897](https://www.dkscore.com/jyotishmedium/festival/swaminarayan-jayanti-2025-2025-178-897)  
44. Murti-Pratishtha of Shri Nilkanth Varni Maharaj, Parsippany, NJ, USA \- BAPS, accessed April 8, 2026, [https://www.baps.org/News/2024/Murti-Pratishtha-of-Shri-Nilkanth-Varni-Maharaj-26449.aspx](https://www.baps.org/News/2024/Murti-Pratishtha-of-Shri-Nilkanth-Varni-Maharaj-26449.aspx)  
45. Murti-Pratishtha Mahotsav of Shri Nilkanth Varni Maharaj, Detroit, MI, USA \- BAPS, accessed April 8, 2026, [https://www.baps.org/News/2023/Murti-Pratishtha-Mahotsav-of-Shri-Nilkanth-Varni-Maharaj-24311.aspx](https://www.baps.org/News/2023/Murti-Pratishtha-Mahotsav-of-Shri-Nilkanth-Varni-Maharaj-24311.aspx)  
46. Festivals \- – BAPS Shri Swaminarayan Mandir, London, accessed April 8, 2026, [http://londonmandir.baps.org/worship/festivals/](http://londonmandir.baps.org/worship/festivals/)  
47. Ashadhi Bij: The Kutchi New Year \- GKToday, accessed April 8, 2026, [https://www.gktoday.in/ashadhi-bij-the-kutchi-new-year/](https://www.gktoday.in/ashadhi-bij-the-kutchi-new-year/)  
48. Ashadhi Bij \- IAS Gyan, accessed April 8, 2026, [https://www.iasgyan.in/daily-current-affairs/ashadhi-bij](https://www.iasgyan.in/daily-current-affairs/ashadhi-bij)  
49. Ashadhi Bij, the new year celebration \- GS Score, accessed April 8, 2026, [https://iasscore.in/current-affairs/prelims/ashadhi-bij-the-new-year-celebration](https://iasscore.in/current-affairs/prelims/ashadhi-bij-the-new-year-celebration)  
50. Ashadhi Bij Festival: Significance for UPSC Current Affairs \- IAS Gyan, accessed April 8, 2026, [https://www.iasgyan.in/daily-current-affairs/ashadhi-bij-9](https://www.iasgyan.in/daily-current-affairs/ashadhi-bij-9)  
51. Ashadhi Bij: Kutchi new year and global recognition \- Organiser, accessed April 8, 2026, [https://organiser.org/2025/06/27/299665/bharat/ashadhi-bij-kutchi-new-year-heralds-monsoon-tradition-global-recognition-as-pm-modi-salutes-communitys-spirit/](https://organiser.org/2025/06/27/299665/bharat/ashadhi-bij-kutchi-new-year-heralds-monsoon-tradition-global-recognition-as-pm-modi-salutes-communitys-spirit/)  
52. It's Kutchy New Year : The Sweetness of Ashadhi Beej \- RJ ViSHAL, accessed April 8, 2026, [https://rjvishal.com/2025/06/27/its-kutchy-new-year/](https://rjvishal.com/2025/06/27/its-kutchy-new-year/)  
53. Ashadhi Beej \- Hindu Priest Ketul Joshi Maharaj, accessed April 8, 2026, [https://hindupriestketuljoshi.co.uk/ashadhi-beej/](https://hindupriestketuljoshi.co.uk/ashadhi-beej/)  
54. Kachchh Festivals and Fairs \- Kutchi Maadu, accessed April 8, 2026, [https://kutchimaadu.com/general/kutchfestivals-fairs-kutch-utsav-rann-utsav/](https://kutchimaadu.com/general/kutchfestivals-fairs-kutch-utsav-rann-utsav/)  
55. Fair and Festivals \- AsanjoKutch.com, accessed April 8, 2026, [http://www.asanjokutch.com/content/fair.asp](http://www.asanjokutch.com/content/fair.asp)  
56. The Most Colorful Fairs and Festivals of Kutch | Blog, accessed April 8, 2026, [https://www.kutchtourguide.com/blog/the-most-colorful-fairs-and-festivals-of-kutch/](https://www.kutchtourguide.com/blog/the-most-colorful-fairs-and-festivals-of-kutch/)  
57. Fair and Festivals \- AsanjoKutch.com, accessed April 8, 2026, [http://asanjokutch.com/content/fair3.asp](http://asanjokutch.com/content/fair3.asp)  
58. Mota Jakh Fair \- Panjokutch, accessed April 8, 2026, [http://www.panjokutch.com/travel/Fairs/Jakh%20Fair.htm](http://www.panjokutch.com/travel/Fairs/Jakh%20Fair.htm)  
59. Fairs & Festivals | District Kachchh, Government of Gujarat | India, accessed April 8, 2026, [https://kachchh.nic.in/fairs-festivals/](https://kachchh.nic.in/fairs-festivals/)  
60. mota yaksh fair \- UTSAV, accessed April 8, 2026, [https://utsav.gov.in/view-event/mota-yaksh-fair](https://utsav.gov.in/view-event/mota-yaksh-fair)  
61. Ravechi Fair in Gujarat \- Tour My India, accessed April 8, 2026, [https://www.tourmyindia.com/states/gujarat/ravechi-fair.html](https://www.tourmyindia.com/states/gujarat/ravechi-fair.html)  
62. ravechi fair 2025 \- UTSAV, accessed April 8, 2026, [https://utsav.gov.in/view-event/ravechi-fair-2025](https://utsav.gov.in/view-event/ravechi-fair-2025)  
63. Ravechi Fair in Gujarat | Gujarat Tour and Travels Organizer, accessed April 8, 2026, [https://www.gujarattravelpackages.com/festivals/ravechi/](https://www.gujarattravelpackages.com/festivals/ravechi/)  
64. Fairs of India \- DHARANG FAIR, accessed April 8, 2026, [http://www.aryabhatt.com/fast\_fair\_festival/Fairs/Dharang\_Fair.htm](http://www.aryabhatt.com/fast_fair_festival/Fairs/Dharang_Fair.htm)  
65. Fair and Festivals \- AsanjoKutch.com, accessed April 8, 2026, [http://www.asanjokutch.com/content/fair5.asp](http://www.asanjokutch.com/content/fair5.asp)  
66. Mekan Dada \- Wikipedia, accessed April 8, 2026, [https://en.wikipedia.org/wiki/Mekan\_Dada](https://en.wikipedia.org/wiki/Mekan_Dada)  
67. Fairs and Festivals in Gujarat \- Tarnetar Festival \- Tour My India, accessed April 8, 2026, [https://www.tourmyindia.com/states/gujarat/tarnetar-festival.html](https://www.tourmyindia.com/states/gujarat/tarnetar-festival.html)  
68. Tarnetar Fair \- Gujarat Tourism, accessed April 8, 2026, [https://gujarattourism.com/fair-and-festival/tarnetar-fair.html](https://gujarattourism.com/fair-and-festival/tarnetar-fair.html)  
69. Tarnetar Festival – Ambika Niwas Palace, accessed April 8, 2026, [https://ambikanivaspalace.com/festivals-attractions/tarnetar-festival/](https://ambikanivaspalace.com/festivals-attractions/tarnetar-festival/)  
70. Uttarayan 2027 Date \- Panchang, accessed April 8, 2026, [https://panchang.astrosage.com/festival/uttarayan](https://panchang.astrosage.com/festival/uttarayan)  
71. Uttarayana \- Wikipedia, accessed April 8, 2026, [https://en.wikipedia.org/wiki/Uttarayana](https://en.wikipedia.org/wiki/Uttarayana)  
72. Uttarayan 2026: When It Begins, Puja Rituals & Religious Significance \- Sanatangyan, accessed April 8, 2026, [https://www.sanatangyan.com/post/uttarayan-2026-when-it-begins-puja-rituals-religious-significance](https://www.sanatangyan.com/post/uttarayan-2026-when-it-begins-puja-rituals-religious-significance)  
73. 2027 Uttarayana Date for New Delhi, NCT, India \- Drik Panchang, accessed April 8, 2026, [https://www.drikpanchang.com/festivals/sankranti/uttarayana/uttarayana-date-time.html](https://www.drikpanchang.com/festivals/sankranti/uttarayana/uttarayana-date-time.html)  
74. Vaikuntha Ekadashi \- Wikipedia, accessed April 8, 2026, [https://en.wikipedia.org/wiki/Vaikuntha\_Ekadashi](https://en.wikipedia.org/wiki/Vaikuntha_Ekadashi)  
75. Why is Vaikunta Ekadasi Falling on a Different Date? Does It Change Every Year?, accessed April 8, 2026, [https://www.omspiritualshop.com/blogs/news/why-is-vaikunta-ekadasi-falling-on-a-different-date-does-it-change-every-year](https://www.omspiritualshop.com/blogs/news/why-is-vaikunta-ekadasi-falling-on-a-different-date-does-it-change-every-year)  
76. Significance of Vaikuntha Ekadashi | PDF | Social Science | Philosophy \- Scribd, accessed April 8, 2026, [https://www.scribd.com/doc/188925028/Benefits](https://www.scribd.com/doc/188925028/Benefits)  
77. Vaikuntha Ekadashi 2025: Date, Significance, Puja Vidhi & Fasting Rules \- Astropatri, accessed April 8, 2026, [https://astropatri.com/blog/vaikuntha-ekadashi-2025](https://astropatri.com/blog/vaikuntha-ekadashi-2025)  
78. Kandha Sasti Vratham 2025 – 6 Days Fasting Rules, Dos and Don'ts, Powerful Murugan Devotional Tips \- YouTube, accessed April 8, 2026, [https://www.youtube.com/watch?v=ngrQG8TLEAU](https://www.youtube.com/watch?v=ngrQG8TLEAU)  
79. Monthly Skanda Shashti Vrat: Remedy for Diseases, Fear, Anxiety, and Conflicts \- AstroVed, accessed April 8, 2026, [https://www.astroved.com/blogs/monthly-skanda-shashti-vrat-remedy-for-diseases-fear-anxiety-and-conflicts](https://www.astroved.com/blogs/monthly-skanda-shashti-vrat-remedy-for-diseases-fear-anxiety-and-conflicts)  
80. Pongal 2026: Dates, Traditions, and How to Celebrate \- Remitly, accessed April 8, 2026, [https://www.remitly.com/blog/lifestyle-culture/pongal-traditions-and-celebrations/](https://www.remitly.com/blog/lifestyle-culture/pongal-traditions-and-celebrations/)  
81. Pongal | Harvest Festival, Rituals, Tamil Nadu, India, & Rice | Britannica, accessed April 8, 2026, [https://www.britannica.com/topic/Pongal](https://www.britannica.com/topic/Pongal)  
82. Onam 2026: Dates, History, Traditions, Rituals, and Celebrations \- Paytm, accessed April 8, 2026, [https://paytm.com/blog/holiday-list/onam-festival/](https://paytm.com/blog/holiday-list/onam-festival/)  
83. Onam Festival 2026: Date, Traditions, Rituals, Celebrations and History \- NoBroker, accessed April 8, 2026, [https://www.nobroker.in/blog/onam/](https://www.nobroker.in/blog/onam/)  
84. Onam \- Wikipedia, accessed April 8, 2026, [https://en.wikipedia.org/wiki/Onam](https://en.wikipedia.org/wiki/Onam)  
85. Question on Onam Date : r/Kerala \- Reddit, accessed April 8, 2026, [https://www.reddit.com/r/Kerala/comments/169fuiq/question\_on\_onam\_date/](https://www.reddit.com/r/Kerala/comments/169fuiq/question_on_onam_date/)

[image1]: <data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABsAAAAXCAYAAAD6FjQuAAABZ0lEQVR4Xu2UPyiFURjGH+S/lFJKUjYxmMxisZIZuySkTMJEKQYGAxmI0WUVZZMY2KXYbAaJEs/rPZ/7fud+p3s/ZVD3V7+65zmn7/n+nHuAIn9ECW3zQ0cl3aRP9JHu0ZbYigJppgP0hB56cxEHdJ020UH6TO9pvVnTRZfoKu02+Q8T9JZu0Hckl/XSc+iTR4zRT7rixh10m1Y7t6DlQV6QXDYLvZEpk8krlDJ5OmGStmanv+dnzDiHUNk09MI7JmtwmXxDYRy5ZfLWgoTKyuko4t+nB1p25saNdBnZ1zjvsiChsiRkZ0pZv8lq6DAdoVUmT6TQsnb6Qef8iTRIWcYPPeTubxD/fr8iX5ls/X3oFi/z5lIjZUd+aFiA3kypydbM71RI2bEfOoboJa01WQX0z54aOfte6SniJ4XQCT2erqFH2hV9oG9016zLSx+9gBbJVhbvoBetc2vkSIrmfBfdmiJF/glf3tVN1zdAkjsAAAAASUVORK5CYII=>