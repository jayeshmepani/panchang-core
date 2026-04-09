<?php

declare(strict_types=1);

namespace JayeshMepani\PanchangCore\Core;

/**
 * Localization Service.
 */
class Localization
{
    private static array $translations = [
        'Nakshatra' => [
            'en' => ['Ashwini','Bharani','Krittika','Rohini','Mrigashira','Ardra','Punarvasu','Pushya','Ashlesha','Magha','Purva Phalguni','Uttara Phalguni','Hasta','Chitra','Swati','Vishakha','Anuradha','Jyeshtha','Mula','Purva Ashadha','Uttara Ashadha','Shravana','Dhanishta','Shatabhisha','Purva Bhadrapada','Uttara Bhadrapada','Revati'],
            'hi' => ['अश्विनी','भरणी','कृत्तिका','रोहिणी','मृगशिरा','आर्द्रा','पुनर्वसु','पुष्य','आश्लेषा','मघा','पूर्व फाल्गुनी','उत्तर फाल्गुनी','हस्त','चित્રા','स्वाती','विशाखा','अनुराधा','ज्येष्ठा','मूला','पूर्वाषाढ़ा','उत्तराषाढ़ा','श्रवण','धनिष्ठा','शतभिषा','पूर्व भाद्रपदा','उत्तर भाद्रपदा','रेवती'],
            'gu' => ['અશ્વિની','ભરણી','કૃત્તિકા','રોહિણી','મૃગશિરા','આર્દ્રા','પુનર્વસુ','પુષ્ય','આશ્લેષા','મઘા','પૂર્વ ફાલ્ગુની','ઉત્તર ફાલ્ગુની','હસ્ત','ચિત્રા','સ્વાતિ','વિશાખા','અનુરાધા','જ્યેષ્ઠા','મૂળ','પૂર્વાષાઢા','ઉત્તરાષાઢા','શ્રવણ','ધનિષ્ઠા','શતભિષા','પૂર્વ ભાદ્રપદા','ઉત્તર ભાદ્રપદા','રેવતી'],
        ],
        'Vara' => [
            'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'hi' => ['रविवार', 'सोमवार', 'मंगलवार', 'बुधवार', 'गुरुवार', 'शुक्रवार', 'शनिवार'],
            'gu' => ['રવિવાર', 'સોમવાર', 'મંગળવાર', 'બુધવાર', 'ગુરૂવાર', 'શુક્રવાર', 'શનિવાર'],
        ],
        'Tithi' => [
            'en' => [1 => 'Pratipada', 2 => 'Dwitiya', 3 => 'Tritiya', 4 => 'Chaturthi', 5 => 'Panchami', 6 => 'Shashthi', 7 => 'Saptami', 8 => 'Ashtami', 9 => 'Navami', 10 => 'Dashami', 11 => 'Ekadashi', 12 => 'Dwadashi', 13 => 'Trayodashi', 14 => 'Chaturdashi', 15 => 'Purnima', 30 => 'Amavasya'],
            'hi' => [1 => 'प्रतिपदा', 2 => 'द्वितीया', 3 => 'तृतीया', 4 => 'चतुर्थी', 5 => 'पंचमी', 6 => 'षष्ठी', 7 => 'सप्तमी', 8 => 'अष्टमी', 9 => 'नवमी', 10 => 'दशमी', 11 => 'एकादशी', 12 => 'द्वादशी', 13 => 'त्रयोदशी', 14 => 'चतुर्दशी', 15 => 'पूर्णिमा', 30 => 'अमावस्या'],
            'gu' => [1 => 'પ્રતિપદા', 2 => 'દ્વિતિયા', 3 => 'તૃતિયા', 4 => 'ચતુર્થી', 5 => 'પંચમી', 6 => 'ષષ્ઠી', 7 => 'સપ્તમી', 8 => 'અષ્ટમી', 9 => 'નવમી', 10 => 'દશમી', 11 => 'એકાદશી', 12 => 'દ્વાદશી', 13 => 'ત્રયોદશી', 14 => 'ચતુર્દશી', 15 => 'પૂર્ણિમા', 30 => 'અમાવસ્યા'],
        ],
        'Rasi' => [
            'en' => ['Mesha', 'Vrishabha', 'Mithuna', 'Karka', 'Simha', 'Kanya', 'Tula', 'Vrischika', 'Dhanu', 'Makara', 'Kumbha', 'Meena'],
            'hi' => ['मेष', 'वृषभ', 'मिथुन', 'कर्क', 'सिंह', 'कन्या', 'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुंभ', 'मीन'],
            'gu' => ['મેષ', 'વૃષભ', 'મિથુન', 'કર્ક', 'સિંહ', 'કન્યા', 'તુલા', 'વૃશ્ચિક', 'ધનુ', 'મકર', 'કુંભ', 'મીન'],
        ],
        'Yoga' => [
            'en' => ['Vishkumbha', 'Priti', 'Ayushman', 'Saubhagya', 'Shobhana', 'Atiganda', 'Sukarma', 'Dhriti', 'Shula', 'Ganda', 'Vriddhi', 'Dhruva', 'Vyaghata', 'Harshana', 'Vajra', 'Siddhi', 'Vyatipata', 'Variyana', 'Parigha', 'Shiva', 'Siddha', 'Sadhya', 'Shubha', 'Shukla', 'Brahma', 'Indra', 'Vaidhriti'],
            'hi' => ['विष्कुम्भ', 'प्रीति', 'आयुष्मान', 'सौभाग्य', 'शोभन', 'अतिगण्ड', 'सुकर्मा', 'धृति', 'शूल', 'गण्ड', 'वृद्धि', 'ध्रुव', 'व्याघात', 'हर्षण', 'वज्र', 'सिद्धि', 'व्यतिपात', 'वरीयान', 'परिघ', 'शिव', 'सिद्ध', 'साध्य', 'शुभ', 'शुक्ल', 'ब्रह्म', 'इन्द्र', 'वैधृति'],
            'gu' => ['વિષ્કુંભ', 'પ્રીતિ', 'આયુષ્માન', 'સૌભાગ્ય', 'શોભન', 'અતિગંડ', 'સુકર્મા', 'ધૃતિ', 'શૂલ', 'ગંડ', 'વૃદ્ધિ', 'ધ્રુવ', 'વ્યાઘાત', 'હર્ષણ', 'વજ્ર', 'સિદ્ધિ', 'વ્યતિપાત', 'વરીયાન', 'પરીઘ', 'શિવ', 'સિદ્ધ', 'સાધ્ય', 'શુભ', 'શુક્લ', 'બ્રહ્મ', 'ઇન્દ્ર', 'વૈધૃતિ'],
        ],
        'Karana' => [
            'en' => ['Bava', 'Balava', 'Kaulava', 'Taitila', 'Gara', 'Vanija', 'Vishti', 'Shakuni', 'Chatushpada', 'Naga', 'Kintughna'],
            'hi' => ['बव', 'बालव', 'कौलव', 'तैतिल', 'गर', 'वणिज', 'विष्टि', 'शकुनि', 'चतुष्पद', 'नाग', 'किस्तुघ्न'],
            'gu' => ['બવ', 'બાલવ', 'કૌલવ', 'તૈતિલ', 'ગર', 'વણિજ', 'વિષ્ટિ', 'શકુનિ', 'ચતુષ્પદ', 'નાગ', 'કિંસ્તુઘ્ન'],
        ],
        'Choghadiya' => [
            'en' => ['Udveg', 'Chal', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog'],
            'hi' => ['उद्वेग', 'चल', 'लाभ', 'अमृत', 'काल', 'शुभ', 'रोग'],
            'gu' => ['ઉદવેગ', 'ચલ', 'લાભ', 'અમૃત', 'કાલ', 'શુભ', 'રોગ'],
        ],
        'Muhurta' => [
            'en' => [
                0 => 'Rudra', 1 => 'Sarpa', 2 => 'Mitra', 3 => 'Pitri', 4 => 'Vasu', 5 => 'Vara', 6 => 'Vishvedeva', 7 => 'Vidhi', 8 => 'Brahma', 9 => 'Indra', 10 => 'Indragni', 11 => 'Daitya', 12 => 'Varuna', 13 => 'Aryaman', 14 => 'Bhaga',
                15 => 'Ishvara', 16 => 'Ajapada', 17 => 'Ahirbudhnya', 18 => 'Pushya', 19 => 'Nasatya', 20 => 'Yama', 21 => 'Vahni', 22 => 'Dhala', 23 => 'Shashi', 24 => 'Aditya', 25 => 'Guru', 26 => 'Acyuta', 27 => 'Arka', 28 => 'Tvashta', 29 => 'Vayu'
            ],
            'hi' => [
                0 => 'रुद्र', 1 => 'सर्प', 2 => 'मित्र', 3 => 'पितृ', 4 => 'वसु', 5 => 'वाराह', 6 => 'विश्वेदेवा', 7 => 'विधि', 8 => 'ब्रह्मा', 9 => 'इन्द्र', 10 => 'इन्द्राग्नि', 11 => 'दैत्य', 12 => 'वरुण', 13 => 'अर्यमा', 14 => 'भग',
                15 => 'ईश्वर', 16 => 'अजपाद', 17 => 'अहिर्बुध्न्य', 18 => 'पुष्य', 19 => 'नासत्य', 20 => 'यम', 21 => 'वह्नि', 22 => 'धला', 23 => 'शशि', 24 => 'आदित्य', 25 => 'गुरु', 26 => 'अच्युत', 27 => 'अर्क', 28 => 'त्वष्टा', 29 => 'वायु'
            ],
            'gu' => [
                0 => 'રુદ્ર', 1 => 'સર્પ', 2 => 'મિત્ર', 3 => 'પિતૃ', 4 => 'વસુ', 5 => 'વારાહ', 6 => 'વિશ્વેદેવા', 7 => 'વિધિ', 8 => 'બ્રહ્મા', 9 => 'ઇન્દ્ર', 10 => 'ઇન્દ્રાજ્ઞિ', 11 => 'દૈત્ય', 12 => 'વરુણ', 13 => 'અર્યમા', 14 => 'ભગ',
                15 => 'ઈશ્વર', 16 => 'અજપાદ', 17 => 'અહિર્બુધ્ન્ય', 18 => 'પુષ્ય', 19 => 'નાસત્ય', 20 => 'યમ', 21 => 'વહ્નિ', 22 => 'ધલા', 23 => 'શશિ', 24 => 'આદિત્ય', 25 => 'ગુરુ', 26 => 'અચ્યુત', 27 => 'અર્ક', 28 => 'ત્વષ્ટા', 29 => 'વાયુ'
            ],
        ],
        'Masa' => [
            'en' => ['Chaitra', 'Vaishakha', 'Jyeshtha', 'Ashadha', 'Shravana', 'Bhadrapada', 'Ashvina', 'Kartika', 'Margashirsha', 'Pausha', 'Magha', 'Phalguna'],
            'hi' => ['चैत्र', 'वैशाख', 'ज्येष्ठ', 'आषाढ़', 'श्रावण', 'भाद्रपद', 'अश्विन', 'कार्तिक', 'मार्गशीर्ष', 'पौष', 'माघ', 'फाल्गुन'],
            'gu' => ['ચૈત્ર', 'વૈશાખ', 'જેઠ', 'અષાઢ', 'શ્રાવણ', 'ભાદરવો', 'આસો', 'કારતક', 'માગશર', 'પોષ', 'મહા', 'ફાગણ'],
        ],
        'Paksha' => [
            'en' => ['Shukla Paksha (waxing)', 'Krishna Paksha (waning)'],
            'hi' => ['शुक्ल', 'कृष्ण'],
            'gu' => ['શુક્લ', 'કૃષ્ણ'],
        ],
        'Ritu' => [
            'en' => ['Vasanta', 'Grishma', 'Varsha', 'Sharad', 'Hemanta', 'Shishira'],
            'hi' => ['वसंत', 'ग्रीष्म', 'वर्षा', 'शरद', 'हेमंत', 'शिशिर'],
            'gu' => ['વસંત', 'ગ્રીષ્મ', 'વર્ષા', 'શરદ', 'હેમંત', 'શિશિર'],
        ],
        'Ayana' => [
            'en' => ['Uttarayana', 'Dakshinayana'],
            'hi' => ['उत्तरायण', 'दक्षिणायन'],
            'gu' => ['ઉત્તરાયણ', 'દક્ષિણાયન'],
        ],
        'Planet' => [
            'en' => ['Sun', 'Moon', 'Mars', 'Rahu', 'Jupiter', 'Saturn', 'Mercury', 'Ketu', 'Venus'],
            'hi' => ['सूर्य', 'चन्द्र', 'मंगल', 'राहु', 'गुरु', 'शनि', 'बुध', 'केतु', 'शुक्र'],
            'gu' => ['સૂર્ય', 'ચંદ્ર', 'મંગળ', 'રાહુ', 'ગુરૂ', 'શનિ', 'બુધ', 'કેતુ', 'શુક્ર'],
        ],
        'Samvatsara' => [
            'en' => ['Prabhava','Vibhava','Shukla','Pramoda','Prajapati','Angirasa','Srimukha','Bhava','Yuva','Dhata','Ishvara','Bahudhanya','Pramathi','Vikrama','Vrisha','Chitrabhanu','Svabhanu','Tarana','Parthiva','Vyaya','Sarvajit','Sarvadhari','Virodi','Vikriti','Khara','Nandana','Vijaya','Jaya','Manmatha','Durmukhi','Hevilambi','Vilambi','Vikari','Sharvari','Plava','Shubhakritu','Shobhakritu','Krodhi','Vishvavasu','Parabhava','Plavanga','Kilaka','Saumya','Sadharana','Virodhikritu','Paritapi','Pramadi','Ananda','Rakshasa','Nala','Pingala','Kalayukti','Siddharthi','Raudri','Durmati','Dundubhi','Rudhirodgari','Raktakshi','Krodhana','Akshaya'],
            'hi' => ['प्रभव','विभव','शुक्ल','प्रमोद','प्रजापति','अंगिरा','श्रीमुख','भाव','युवा','धाता','ईश्वर','बहुधान्य','प्रमाथी','विक्रम','वृष','चित्रभानु','स्वभानु','तारण','पार्थिव','व्यय','सर्वजित','सर्वधारी','विरोधी','विकृति','खर','नन्दन','विजय','जय','मन्मथ','दुर्मुख','हेविलम्बी','विलम्बी','विकारी','शर्वरी','प्लव','शुभकृत','शोभनकृत','क्रोधी','विश्वावसु','पराभव','प्लवंग','कीलक','सौम्य','साधारण','विरोधकृत','परितापी','प्रमादी','आनन्द','राक्षस','नल','पिंगल','कालयुक्त','सिद्धार्थी','रौद्र','दुर्मति','दुन्दुभी','रुधिरोद्गारी','रक्ताक्ष','क्रोधन','अक्षय'],
            'gu' => ['પ્રભવ','વિભવ','શુક્લ','પ્રમોદ','પ્રજાપતિ','અંગિરા','શ્રીમુખ','ભાવ','યુવા','ધાતા','ઈશ્વર','બહુધાન્ય','પ્રમાથી','વિક્રમ','વૃષ','ચિત્રભાનુ','સ્વભાનુ','તારણ','પાર્થિવ','વ્યય','સર્વજિત','સર્વધારી','વિરોધી','વિૃતિ','ખર','નંદન','વિજય','જય','મન્મથ','દુર્મુખ','હેવિલંબી','વિલંબી','વિકારી','શાર્વરી','પ્લવ','શુભકૃત','શોભનકૃત','ક્રોધી','વિશ્વાવસુ','પરાભવ','પ્લવંગ','કીલક','સૌમ્ય','સાધારણ','વિરોધકૃત','પરિતાપી','પ્રમાદી','આનંદ','રાક્ષસ','નલ','પિંગલ','કાલયુક્ત','સિદ્ધાર્થી','રૌદ્ર','દુર્મતિ','દુંદુભી','રુધિરોદ્ગારી','રક્તાક્ષ','ક્રોધન','અક્ષય'],
        ],
        'Vrata' => [
            'en' => [
                'Pratipada Vrata' => 'Pratipada Vrata',
                'Dwitiya Vrata' => 'Dwitiya Vrata',
                'Tritiya Vrata' => 'Tritiya Vrata',
                'Chaturthi Vrata' => 'Chaturthi Vrata',
                'Panchami Vrata' => 'Panchami Vrata',
                'Shashthi Vrata' => 'Shashthi Vrata',
                'Saptami Vrata' => 'Saptami Vrata',
                'Ashtami Vrata' => 'Ashtami Vrata',
                'Navami Vrata' => 'Navami Vrata',
                'Dashami Vrata' => 'Dashami Vrata',
                'Ekadashi Vrata' => 'Ekadashi Vrata',
                'Dwadashi Vrata' => 'Dwadashi Vrata',
                'Trayodashi Vrata' => 'Trayodashi Vrata',
                'Chaturdashi Vrata' => 'Chaturdashi Vrata',
                'Purnima / Amavasya Vrata' => 'Purnima / Amavasya Vrata',
            ],
            'hi' => [
                'Pratipada Vrata' => 'प्रतिपदा व्रत',
                'Dwitiya Vrata' => 'द्वितीया व्रत',
                'Tritiya Vrata' => 'तृतीया व्रत',
                'Chaturthi Vrata' => 'चतुर्थी व्रत',
                'Panchami Vrata' => 'पंचमी व्रत',
                'Shashthi Vrata' => 'षष्ठी व्रत',
                'Saptami Vrata' => 'सप्तमी व्रत',
                'Ashtami Vrata' => 'अष्टमी व्रत',
                'Navami Vrata' => 'नवमी व्रत',
                'Dashami Vrata' => 'दशमी व्रत',
                'Ekadashi Vrata' => 'एकादशी व्रत',
                'Dwadashi Vrata' => 'द्वादशी व्रत',
                'Trayodashi Vrata' => 'त्रयोदशी व्रत',
                'Chaturdashi Vrata' => 'चतुर्दशी व्रत',
                'Purnima / Amavasya Vrata' => 'पूर्णिमा / अमावस्या व्रत',
            ],
            'gu' => [
                'Pratipada Vrata' => 'પ્રતિપદા વ્રત',
                'Dwitiya Vrata' => 'દ્વિતિયા વ્રત',
                'Tritiya Vrata' => 'તૃતિયા વ્રત',
                'Chaturthi Vrata' => 'ચતુર્થી વ્રત',
                'Panchami Vrata' => 'પંચમી વ્રત',
                'Shashthi Vrata' => 'ષષ્ઠી વ્રત',
                'Saptami Vrata' => 'સપ્તમી વ્રત',
                'Ashtami Vrata' => 'અષ્ટમી વ્રત',
                'Navami Vrata' => 'નવમી વ્રત',
                'Dashami Vrata' => 'દશમી વ્રત',
                'Ekadashi Vrata' => 'એકાદશી વ્રત',
                'Dwadashi Vrata' => 'દ્વાદશી વ્રત',
                'Trayodશી વ્રત' => 'ત્રયોદશી વ્રત',
                'Chaturdashi Vrata' => 'ચતુર્દશી વ્રત',
                'Purnima / Amavasya Vrata' => 'પૂર્ણિમા / અમાવસ્યા વ્રત',
            ],
        ],
        'Festival' => [
            'en' => [
                'Hanuman Jayanti' => 'Hanuman Jayanti',
                'Rama Navami' => 'Rama Navami',
                'Ganesh Chaturthi' => 'Ganesh Chaturthi',
                'Diwali' => 'Diwali',
                'Holi' => 'Holi',
                'Maha Shivaratri' => 'Maha Shivaratri',
                'Janmashtami' => 'Janmashtami',
                'Navaratri' => 'Navaratri',
                'Dussehra' => 'Dussehra',
                'Raksha Bandhan' => 'Raksha Bandhan',
            ],
            'hi' => [
                'Hanuman Jayanti' => 'हनुमान जयंती',
                'Rama Navami' => 'राम नवमी',
                'Ganesh Chaturthi' => 'गणेश चतुर्थी',
                'Diwali' => 'दीपावली',
                'Holi' => 'होली',
                'Maha Shivaratri' => 'महा शिवरात्रि',
                'Janmashtami' => 'कृष्ण जन्माष्टमी',
                'Navaratri' => 'नवरात्रि',
                'Dussehra' => 'दशहरा',
                'Raksha Bandhan' => 'रक्षाबंधन',
            ],
            'gu' => [
                'Hanuman Jayanti' => 'હનૂમાન જયંતિ',
                'Rama Navami' => 'રામ નવમી',
                'Ganesh Chaturthi' => 'ગણેશ ચતુર્થી',
                'Diwali' => 'દિવાળી',
                'Holi' => 'હોળી',
                'Maha Shivaratri' => 'મહા શિવરાત્રી',
                'Janmashtami' => 'જન્માષ્ટમી',
                'Navaratri' => 'નવરાત્રી',
                'Dussehra' => 'દશેરા',
                'Raksha Bandhan' => 'રક્ષાબંધન',
            ],
        ],
        'Panchaka' => [
            'en' => [
                'Mrityu' => 'Mrityu', 'Death' => 'Death',
                'Agni' => 'Agni', 'Fire' => 'Fire',
                'Rahita' => 'Rahita',
                'Raja' => 'Raja', 'King' => 'King',
                'Chora' => 'Chora', 'Thief' => 'Thief',
                'Roga' => 'Roga', 'Disease' => 'Disease',
            ],
            'hi' => [
                'Mrityu' => 'मृत्यु', 'Death' => 'मृत्यु',
                'Agni' => 'अग्नि', 'Fire' => 'अग्नि',
                'Rahita' => 'रहित',
                'Raja' => 'राज', 'King' => 'राज',
                'Chora' => 'चोर', 'Thief' => 'चोर',
                'Roga' => 'रोग', 'Disease' => 'रोग',
            ],
            'gu' => [
                'Mrityu' => 'મૃત્યુ', 'Death' => 'મૃત્યુ',
                'Agni' => 'અગ્નિ', 'Fire' => 'અગ્નિ',
                'Rahita' => 'રહિત',
                'Raja' => 'રાજ', 'King' => 'રાજ',
                'Chora' => 'ચોર', 'Thief' => 'ચોર',
                'Roga' => 'રોગ', 'Disease' => 'રોગ',
            ],
        ],
        'Moorthy' => [
            'en' => [
                'Suvarna' => 'Suvarna', 'Gold' => 'Gold',
                'Rajata' => 'Rajata', 'Silver' => 'Silver',
                'Tamra' => 'Tamra', 'Copper' => 'Copper',
                'Lauha' => 'Lauha', 'Iron' => 'Iron',
            ],
            'hi' => [
                'Suvarna' => 'सुवर्ण', 'Gold' => 'सुवर्ण (सोना)',
                'Rajata' => 'रजत', 'Silver' => 'रजत (चांदी)',
                'Tamra' => 'ताम्र', 'Copper' => 'ताम्र (तांबा)',
                'Lauha' => 'लौह', 'Iron' => 'लौह (लोहा)',
            ],
            'gu' => [
                'Suvarna' => 'સુવર્ણ', 'Gold' => 'સુવર્ણ',
                'Rajata' => 'રજત', 'Silver' => 'રજત',
                'Tamra' => 'તામ્ર', 'Copper' => 'તામ્ર',
                'Lauha' => 'લોહ', 'Iron' => 'લોહ',
            ],
        ],
        'Gowri' => [
            'en' => [
                'Uthi' => 'Uthi', 'Amirdha' => 'Amirdha', 'Rogam' => 'Rogam', 'Laabam' => 'Laabam',
                'Dhanam' => 'Dhanam', 'Sugam' => 'Sugam', 'Soram' => 'Soram', 'Visham' => 'Visham'
            ],
            'hi' => [
                'Uthi' => 'उथि', 'Amirdha' => 'अमिरधा', 'Rogam' => 'रोगम', 'Laabam' => 'लाभम',
                'Dhanam' => 'धनम', 'Sugam' => 'सुगम', 'Soram' => 'सोरम', 'Visham' => 'विषम'
            ],
            'gu' => [
                'Uthi' => 'ઉથિ', 'Amirdha' => 'અમિરધા', 'Rogam' => 'રોગમ', 'Laabam' => 'લાભમ',
                'Dhanam' => 'ધનમ', 'Sugam' => 'સુગમ', 'Soram' => 'સોરમ', 'Visham' => 'વિષમ'
            ],
        ],
        'Fivefold' => [
            'en' => ['Pratah', 'Sangava', 'Madhyahna', 'Aparahna', 'Sayahna'],
            'hi' => ['प्रातः', 'संगव', 'मध्याह्न', 'अपराह्न', 'सायह्न'],
            'gu' => ['પ્રાતઃ', 'સંગવ', 'મધ્યાહ્ન', 'અપરાહ્ન', 'સાયહ્ન'],
        ],
        'Prahara' => [
            'en' => [
                'Pratah Prahara' => 'Pratah Prahara', 'Sangava Prahara' => 'Sangava Prahara', 'Madhyahna Prahara' => 'Madhyahna Prahara', 'Aparahna Prahara' => 'Aparahna Prahara',
                'Pradosha Prahara' => 'Pradosha Prahara', 'Nishitha Prahara' => 'Nishitha Prahara', 'Triyama Prahara' => 'Triyama Prahara', 'Usha Prahara' => 'Usha Prahara'
            ],
            'hi' => [
                'Pratah Prahara' => 'प्रातः प्रहर', 'Sangava Prahara' => 'संगव प्रहर', 'Madhyahna Prahara' => 'मध्याह्न प्रहर', 'Aparahna Prahara' => 'अपराह्न प्रहर',
                'Pradosha Prahara' => 'प्रदोष प्रहर', 'Nishitha Prahara' => 'निशिथ प्रहर', 'Triyama Prahara' => 'त्रियाम प्रहर', 'Usha Prahara' => 'उषा प्रहर'
            ],
            'gu' => [
                'Pratah Prahara' => 'પ્રાતઃ પ્રહર', 'Sangava Prahara' => 'સંગવ પ્રહર', 'Madhyahna Prahara' => 'મધ્યાહ્ન પ્રહર', 'Aparahna Prahara' => 'અપરાહ્ન પ્રહર',
                'Pradosha Prahara' => 'પ્રદોષ પ્રહર', 'Nishitha Prahara' => 'નિશિથ પ્રહર', 'Triyama Prahara' => 'ત્રિયામ પ્રહર', 'Usha Prahara' => 'ઉષા પ્રહર'
            ],
        ],
        'Common' => [
            'en' => [
                'Adhika' => 'Adhika', 'Kshaya' => 'Kshaya', 'Sankranti' => 'Sankranti', 'Purnima' => 'Purnima', 'Amavasya' => 'Amavasya',
                'Auspicious' => 'Auspicious', 'Inauspicious' => 'Inauspicious', 'Neutral' => 'Neutral', 'Very Auspicious' => 'Very Auspicious',
                'Challenging' => 'Challenging', 'Excellent' => 'Excellent', 'Good' => 'Good', 'Mixed' => 'Mixed',
                'None' => 'None', 'Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High', 'Critical' => 'Critical',
                'Earth' => 'Earth', 'Heaven' => 'Heaven', 'Underworld' => 'Underworld',
                'Sunrise' => 'Sunrise', 'Sunset' => 'Sunset', 'Moonrise' => 'Moonrise', 'Moonset' => 'Moonset',
                'Deity' => 'Deity', 'Benefit' => 'Benefit', 'Description' => 'Description', 'Fasting' => 'Fasting', 'Regions' => 'Regions',
                'rejected' => 'rejected', 'accepted' => 'accepted', 'accepted_with_warnings' => 'accepted_with_warnings', 'rejected_but_can_try_remedies' => 'rejected_but_can_try_remedies',
            ],
            'hi' => [
                'Adhika' => 'अधिक', 'Kshaya' => 'क्षय', 'Sankranti' => 'संक्रांति', 'Purnima' => 'पूर्णिमा', 'Amavasya' => 'अमावस्या',
                'Auspicious' => 'शुभ', 'Inauspicious' => 'अशुभ', 'Neutral' => 'सामान्य', 'Very Auspicious' => 'अति शुभ',
                'Challenging' => 'चुनौतीपूर्ण', 'Excellent' => 'उत्तम', 'Good' => 'अच्छा', 'Mixed' => 'मिश्रित',
                'None' => 'कोई नहीं', 'Low' => 'कम', 'Medium' => 'मध्यम', 'High' => 'उच्च', 'Critical' => 'गंभीर',
                'Earth' => 'पृथ्वी', 'Heaven' => 'स्वर्ग', 'Underworld' => 'पाताल',
                'Sunrise' => 'सूर्योदय', 'Sunset' => 'सूर्यास्त', 'Moonrise' => 'चन्द्रोदय', 'Moonset' => 'चन्द्रास्त',
                'Deity' => 'देवता', 'Benefit' => 'फल', 'Description' => 'विवरण', 'Fasting' => 'व्रत/उपवास', 'Regions' => 'क्षेत्र',
                'rejected' => 'अस्वीकृत', 'accepted' => 'स्वीकृत', 'accepted_with_warnings' => 'चेतावनियों के साथ स्वीकृत', 'rejected_but_can_try_remedies' => 'अस्वीकृत लेकिन उपाय किए जा सकते हैं',
            ],
            'gu' => [
                'Adhika' => 'અધિક', 'Kshaya' => 'ક્ષય', 'Sankranti' => 'સંક્રાંતિ', 'Purnima' => 'પૂર્ણિમા', 'Amavasya' => 'અમાવસ્યા',
                'Auspicious' => 'શુભ', 'Inauspicious' => 'અશુભ', 'Neutral' => 'સામાન્ય', 'Very Auspicious' => 'અતિ શુભ',
                'Challenging' => 'પડકારરૂપ', 'Excellent' => 'ઉત્તમ', 'Good' => 'સારું', 'Mixed' => 'મિશ્રિત',
                'None' => 'કોઈ નહીં', 'Low' => 'ઓછું', 'Medium' => 'મધ્યમ', 'High' => 'ઉચ્ચ', 'Critical' => 'ગંભીર',
                'Earth' => 'પૃથ્વી', 'Heaven' => 'સ્વર્ગ', 'Underworld' => 'પાતાળ',
                'Sunrise' => 'સૂર્યોદય', 'Sunset' => 'સૂર્યાસ્ત', 'Moonrise' => 'ચંદ્રોદય', 'Moonset' => 'ચંદ્રાસ્ત',
                'Deity' => 'દેવતા', 'Benefit' => 'ફળ', 'Description' => 'વર્ણન', 'Fasting' => 'ઉપવાસ', 'Regions' => 'વિસ્તાર',
                'rejected' => 'અસ્વીકૃત', 'accepted' => 'સ્વીકૃત', 'accepted_with_warnings' => 'ચેતવણીઓ સાથે સ્વીકૃત', 'rejected_but_can_try_remedies' => 'અસ્વીકૃત પરંતુ ઉપાયો કરી શકાય છે',
            ],
        ],
        'String' => [
            'en' => [
                'Day' => 'Day', 'Night' => 'Night', 'Rahu Kaal' => 'Rahu Kaal', 'Gulika' => 'Gulika', 'Yamaganda' => 'Yamaganda',
                'Abhijit' => 'Abhijit', 'Nishita' => 'Nishita', 'Vijaya' => 'Vijaya', 'Godhuli' => 'Godhuli',
                'Pratah Sandhya' => 'Pratah Sandhya', 'Madhyahna Sandhya' => 'Madhyahna Sandhya', 'Sayahna Sandhya' => 'Sayahna Sandhya',
                'Brahma Muhurta' => 'Brahma Muhurta', 'Dagdha Tithi' => 'Dagdha Tithi', 'Dagdha Yoga' => 'Dagdha Yoga',
                'Rikta Tithi' => 'Rikta Tithi', 'Varjyam' => 'Varjyam', 'Abhijit Muhurta' => 'Abhijit Muhurta',
                'Varjyam (Visha Ghati)' => 'Varjyam (Visha Ghati)',
                'Sun Sign' => 'Sun Sign', 'Moon Sign' => 'Moon Sign',
                'Muhurta is auspicious. Proceed with confidence.' => 'Muhurta is auspicious. Proceed with confidence.',
                'Muhurta is inauspicious. Finding alternative time recommended.' => 'Muhurta is inauspicious. Finding alternative time recommended.',
                'Muhurta has significant doshas. Remedies may help but alternative preferred.' => 'Muhurta has significant doshas. Remedies may help but alternative preferred.',
                'Muhurta is acceptable but has minor doshas. Consider remedies.' => 'Muhurta is acceptable but has minor doshas. Consider remedies.',
                'Abhijit Muhurta - High Dosha Cancellation Power' => 'Abhijit Muhurta - High Dosha Cancellation Power',
                'Abhijit power cancelled (Wednesday)' => 'Abhijit power cancelled (Wednesday)',
                'Not in Abhijit Muhurta' => 'Not in Abhijit Muhurta',
                'Krishna Paksha (waning)' => 'Krishna Paksha (waning)',
                'Shukla Paksha (waxing)' => 'Shukla Paksha (waxing)',
            ],
            'hi' => [
                'Day' => 'दिन', 'Night' => 'रात्रि', 'Rahu Kaal' => 'राहु काल', 'Gulika' => 'गुलिका', 'Yamaganda' => 'यमघण्ट',
                'Abhijit' => 'अभिजित', 'Nishita' => 'निशिथ', 'Vijaya' => 'विजय', 'Godhuli' => 'गोधूलि',
                'Pratah Sandhya' => 'प्रातः सन्ध्या', 'Madhyahna Sandhya' => 'मध्याह्न सन्ध्या', 'Sayahna Sandhya' => 'सायह्न सन्ध्या',
                'Brahma Muhurta' => 'ब्रह्म मुहूर्त', 'Dagdha Tithi' => 'दग्ध तिथि', 'Dagdha Yoga' => 'दग्ध योग',
                'Rikta Tithi' => 'रिक्ता तिथि', 'Varjyam' => 'वर्ज्यम', 'Abhijit Muhurta' => 'अभिजित मुहूर्त',
                'Varjyam (Visha Ghati)' => 'वर्ज्यम (विष घाटी)',
                'Sun Sign' => 'सूर्य राशि', 'Moon Sign' => 'चन्द्र राशि',
                'Muhurta is auspicious. Proceed with confidence.' => 'मुहूर्त शुभ है। विश्वास के साथ आगे बढ़ें।',
                'Muhurta is inauspicious. Finding alternative time recommended.' => 'मुहूर्त अशुभ है। वैकल्पिक समय खोजने की अनुशंसा की जाती है।',
                'Muhurta has significant doshas. Remedies may help but alternative preferred.' => 'मुहूर्त में महत्वपूर्ण दोष हैं। उपाय मदद कर सकते हैं लेकिन वैकल्पिक समय बेहतर है।',
                'Muhurta is acceptable but has minor doshas. Consider remedies.' => 'मुहूर्त स्वीकार्य है लेकिन इसमें छोटे दोष हैं। उपायों पर विचार करें।',
                'Abhijit Muhurta - High Dosha Cancellation Power' => 'अभिजित मुहूर्त - उच्च दोष निवारण शक्ति',
                'Abhijit power cancelled (Wednesday)' => 'अभिजित शक्ति रद्द (बुधवार)',
                'Not in Abhijit Muhurta' => 'अभिजित मुहूर्त में नहीं',
                'Krishna Paksha (waning)' => 'कृष्ण पक्ष',
                'Shukla Paksha (waxing)' => 'शुक्ल पक्ष',
            ],
            'gu' => [
                'Day' => 'દિવસ', 'Night' => 'રાત્રિ', 'Rahu Kaal' => 'રાહુ કાળ', 'Gulika' => 'ગુલિકા', 'Yamaganda' => 'યમઘંટ',
                'Abhijit' => 'અભિજિત', 'Nishita' => 'નિશિથ', 'Vijaya' => 'વિજય', 'Godhuli' => 'ગોધૂલિ',
                'Pratah Sandhya' => 'પ્રાતઃ સંધ્યા', 'Madhyahna Sandhya' => 'મધ્યાહ્ન સંધ્યા', 'Sayahna Sandhya' => 'સાયહ્ન સંધ્યા',
                'Brahma Muhurta' => 'બ્રહ્મ મુહૂર્ત', 'Dagdha Tithi' => 'દગ્ધ તિથિ', 'Dagdha Yoga' => 'દગ્ધ યોગ',
                'Rikta Tithi' => 'રિક્તા તિથિ', 'Varjyam' => 'વર્જ્યમ', 'Abhijit મુહૂર્ત' => 'અભિજિત મુહૂર્ત',
                'Varjyam (Visha Ghati)' => 'વર્જ્યમ (વિષ ઘાટી)',
                'Sun Sign' => 'સૂર્ય રાશિ', 'Moon Sign' => 'ચંદ્ર રાશિ',
                'Muhurta is auspicious. Proceed with confidence.' => 'મુહૂર્ત શુભ છે. આત્મવિશ્વાસ સાથે આગળ વધો.',
                'Muhurta is inauspicious. Finding alternative time recommended.' => 'મુહૂર્ત અશુભ છે. વૈકલ્પિક સમય શોધવાની ભલામણ કરવામાં આવે છે.',
                'Muhurta has significant doshas. Remedies may help but alternative preferred.' => 'મુહૂર્તમાં નોંધપાત્ર દોષો છે. ઉપાયો મદદ કરી શકે છે પરંતુ વૈકલ્પિક સમય પસંદ કરવામાં આવે છે.',
                'Muhurta is acceptable but has minor doshas. Consider remedies.' => 'મુહૂર્ત સ્વીકાર્ય છે પરંતુ તેમાં ગૌણ દોષો છે. ઉપાયો ધ્યાનમાં લો.',
                'Abhijit Muhurta - High Dosha Cancellation Power' => 'અભિજિત મુહૂર્ત - ઉચ્ચ દોષ નિવારણ શક્તિ',
                'Abhijit power cancelled (Wednesday)' => 'અભિજિત શક્તિ રદ (બુધવાર)',
                'Not in Abhijit Muhurta' => 'અભિજિત મુહૂર્તમાં નથી',
                'Krishna Paksha (waning)' => 'કૃષ્ણ પક્ષ',
                'Shukla Paksha (waxing)' => 'શુક્લ પક્ષ',
            ],
        ],
    ];

    public static function translate(string $type, int|string $key, ?string $locale = null): string
    {
        $locale ??= AstroCore::getConfig('panchang.defaults.locale', 'en');
        if (!isset(self::$translations[$type][$locale])) $locale = 'en';
        if ($type === 'String') return self::$translations['String'][$locale][$key] ?? self::$translations['Common'][$locale][$key] ?? (string) $key;
        return self::$translations[$type][$locale][$key] ?? (string) $key;
    }
}
