# Festival And Vrat Identities

Canonical catalog of festival and vrat identities defined by `FestivalService::FESTIVALS`. The first column is a serial number for reading convenience; **Identity** is the catalog key (and the usual `name_key` when the observance is emitted). **Alias(es)** are alternate public names from registry aliases, traditions, display names, and recent English outputs.

## Catalog totals (general — year-independent)

| Field | Source | Count |
|---|---|---:|
| `total_festivals` | Non-vrat (`fasting` unset/false) first-level `FESTIVALS` keys | **336** |
| `total_vrats` | Fasting keys with `identity_key` collapse + Pradosh expanded to 7 weekday identities | **124** |

These totals are what generated JSON reports in `total_festivals` / `total_vrats`. They do **not** shrink when a definition does not fire in a given year or calendar system. Dated occurrence volume remains separate (`festival_entry_count` / `vrat_entry_count`).

**Do not confuse these layers:**

| Layer | What it is | Current value |
|---|---|---|
| **Catalog totals** | `total_festivals` / `total_vrats` in generated JSON | **336** / **124** |
| **Year-observed unique keys** | Distinct `name_key`s that actually fire in a given year/calendar `by_date` dump | Always ≤ catalog; varies by year |
| **Table `#` column below** | Reading serial only (1…N) | Not a package identity total |

A given year may still emit fewer unique `name_key`s in `by_date` than the catalog because not every definition occurs every year; those rows still count toward the catalog total.

Runtime notes:

- **Pradosh Vrat** expands to seven weekday identities.
- **Rama Navami (Smarta)** and **Rama Navami (Vaishnava)** are distinct identities when dual-day rules differ.
- **Swaminarayan Varaha Jayanti** (Shravana Shukla Chaturthi) is distinct from generic **Varaha Jayanti** (Bhadrapada Tritiya).
- **Kali Chaudas** (sangava / Hanuman) is distinct from **Naraka Chaturdashi Abhyanga Snan** (moonrise bath).
- **Cheti Chand** is Chaitra Shukla Dwitiya under both Amanta and Purnimanta.
- **Monthly Hari Jayanti** (Shukla Navami outside Chaitra) is also published in intercalary (Adhika) months.
- **Shree Hari Antardhan** (Swadhaam Gaman) is a commemorative festival on Jyeshtha Shukla Dashami (Gadhada, VS 1886 / 1 June 1830), not a fasting vrat.

## Festival Identities (Catalog: 336)

| # | Identity | Alias(es) |
|---:|---|---|
| 1 | Aadi Amavasya (Karkidaka Vavu) | - |
| 2 | Aadi Perukku | - |
| 3 | Adhika Bhanu Saptami | - |
| 4 | Adhika Chandra Darshana | - |
| 5 | Adhika Darsha Amavasya | - |
| 6 | Adhika Kalashtami | - |
| 7 | Adhika Krishna Ramalakshmana Dwadashi | - |
| 8 | Adhika Masik Durgashtami | - |
| 9 | Adhika Masik Shivaratri | - |
| 10 | Adhika Ramalakshmana Dwadashi | - |
| 11 | Adhika Skanda Sashti | - |
| 12 | Adhik Masik Krishna Janmashtami | - |
| 13 | Adi Shankaracharya Jayanti | - |
| 14 | Akal Bodhon | - |
| 15 | Akshaya Tritiya | Akshaya Tritiya (Lakshmi-Narayana), Treta Yuga Diwas |
| 16 | Alankar Marjanotsav | Alankar Marjan, Alankar Marjanotsava |
| 17 | Amavasya | Amas, Amavasya Vrat, Bhaumavati Amavasya, Shani Amavasya, Somavati Amavasya |
| 18 | Anant Chaturdashi | Ganesh Visarjan |
| 19 | Aniruddha Chaturthi | - |
| 20 | Anvadhan | - |
| 21 | Arudra Darshan | Ardra Utsav, Arudra Darshanam, Thiruvadhirai |
| 22 | Ashadha Amavasya | Deep Puja, Divaso |
| 23 | Ashadha Gupt Navaratri Day 1 (Ghatasthapana) | - |
| 24 | Ashadha Gupt Navaratri Day 2 | - |
| 25 | Ashadha Gupt Navaratri Day 3 | - |
| 26 | Ashadha Gupt Navaratri Day 4 | - |
| 27 | Ashadha Gupt Navaratri Day 5 | - |
| 28 | Ashadha Gupt Navaratri Day 6 | - |
| 29 | Ashadha Gupt Navaratri Day 7 | - |
| 30 | Ashadha Gupt Navaratri Day 8 | - |
| 31 | Ashadha Gupt Navaratri Day 9 | - |
| 32 | Ashadha Gupt Navaratri Parana (Dashami) | - |
| 33 | Ashadha Purnima | Guru Purnima, Vyasa Puja |
| 34 | Ashadhi Bij | - |
| 35 | Ashvina Sharad Navaratri Day 1 (Shailaputri Puja) | Sharad Navratri Ghatasthapana |
| 36 | Ashvina Sharad Navaratri Day 2 (Brahmacharini Puja) | - |
| 37 | Ashvina Sharad Navaratri Day 3 (Chandraghanta Puja) | - |
| 38 | Ashvina Sharad Navaratri Day 4 (Kushmanda Puja) | - |
| 39 | Ashvina Sharad Navaratri Day 5 (Skandamata Puja) | - |
| 40 | Ashvina Sharad Navaratri Day 6 (Katyayani Puja) | - |
| 41 | Ashvina Sharad Navaratri Day 7 (Kalaratri Puja) | - |
| 42 | Ashvina Sharad Navaratri Day 8 (Mahagauri Puja) | Ashvina Sharad Navaratri Day 8, Durga Ashtami, Durga Ashtami (Mahagauri Puja), Maha Ashtami |
| 43 | Ashvina Sharad Navaratri Day 9 (Siddhidatri Puja) | Ashvina Sharad Navaratri Day 9, Maha Navami, Maha Navami (Siddhidatri Puja) |
| 44 | Ashwina Amavasya | - |
| 45 | Attukal Pongal | - |
| 46 | Avani Avittam (Yajur Upakarma) | - |
| 47 | Avidhava Navami | Adukha Navami, Avidha Navami, Avidhava Shraddha, Matra Navami, Matru Navami, Naumi Shraddha, Navami Shraddha, Saubhagyavati (Vidhwa) Navami, Saubhagyavati Navami |
| 48 | Ayudha Puja (Saraswati Puja) | - |
| 49 | Bagalamukhi Jayanti | - |
| 50 | Bahuda Yatra | - |
| 51 | Bali Pratipada | Bali Puja |
| 52 | Bathukamma (Saddula) | - |
| 53 | Bestu Varas | - |
| 54 | Bhadrapada Amavasya | Mahalaya Amavasya, Sarva Pitru Amavasya |
| 55 | Bhagatji Maharaj Jayanti | - |
| 56 | Bhagavat Saptah Prarambh | Bhagavat Saptaha Begins, Bhagwat Saptah Begins |
| 57 | Bhagavat Saptah Samapt | Bhagavat Saptaha Ends, Bhagwat Saptah Ends |
| 58 | Bhai Dooj | Bhai Tika, Bhau Beej, Yama Dwitiya |
| 59 | Bhishma Ashtami | - |
| 60 | Bhishma Dwadashi | - |
| 61 | Bhishma Panchak Ends | - |
| 62 | Bhogi Pandigai | - |
| 63 | Bilva Nimantran | - |
| 64 | Bol Choth | - |
| 65 | Bonalu (Ashadha Sunday) | - |
| 66 | Brahma Savarni Manvadi | - |
| 67 | Chaiti Chhath | - |
| 68 | Chaitra (Vasant) Navaratri Day 1 (Shailaputri Puja) | Chaitra Navratri Ghatasthapana |
| 69 | Chaitra (Vasant) Navaratri Day 2 (Brahmacharini Puja) | - |
| 70 | Chaitra (Vasant) Navaratri Day 3 (Chandraghanta Puja) | - |
| 71 | Chaitra (Vasant) Navaratri Day 4 (Kushmanda Puja) | - |
| 72 | Chaitra (Vasant) Navaratri Day 5 (Skandamata Puja) | - |
| 73 | Chaitra (Vasant) Navaratri Day 6 (Katyayani Puja) | - |
| 74 | Chaitra (Vasant) Navaratri Day 7 (Kalaratri Puja) | - |
| 75 | Chaitra (Vasant) Navaratri Day 8 (Mahagauri Puja) | - |
| 76 | Chaitra (Vasant) Navaratri Day 9 (Siddhidatri Puja) | - |
| 77 | Chaitra Amavasya | - |
| 78 | Chaitra Purnima | Hanuman Jayanti, Hanuman Jayanti (North Indian) |
| 79 | Chakshusha Manvadi | - |
| 80 | Chandan Yatra Begins | Chandan Yatra, Chandanotsav Begins |
| 81 | Chandika Jayanti | - |
| 82 | Chapchar Kut | - |
| 83 | Cheti Chand | - |
| 84 | Chhinnamasta Jayanti | - |
| 85 | Chitragupta Puja | - |
| 86 | Chitra Pournami | - |
| 87 | Chopda Pujan | Deepavali Puja, Shaaradaa Pujan, Sharada Puja, Sharda Puja |
| 88 | Dada Mekan Fair (Dhrang Mela) | - |
| 89 | Dadhichi Jayanti | - |
| 90 | Daiva Savarni Manvadi | - |
| 91 | Daksha Savarni Manvadi | - |
| 92 | Damodara Dwadashi | - |
| 93 | Dattatreya Jayanti | - |
| 94 | Dayanand Saraswati Jayanti | - |
| 95 | Dev Diwali (Tripurari Purnima) | - |
| 96 | Dhanteras | Dhanatrayodashi, Dhanvantari Jayanti (Dhantrayodashi) |
| 97 | Dhanu Sankranti | Dhanurmas / Early Thal, Dhanurmas Festival Begins, Early Thal Begins, Thakorji Thal Vahela Begins |
| 98 | Dhuleti | Dhulandi |
| 99 | Durga Balidan | - |
| 100 | Durga Visarjan | - |
| 101 | Dussehra | Vijayadashami, Vijayadashami (Aparajita Puja) |
| 102 | Dwapara Yuga Diwas | Mauni Amavasya |
| 103 | Dyuta Krida | - |
| 104 | Ganesha Jayanti | Dhundhiraja Chaturthi, Gauriganesha Chaturthi, Tila Chaturthi, Varada Chaturthi |
| 105 | Ganesh Chaturthi | Siddhivinayaka Chaturthi, Vinayaka Chaturthi |
| 106 | Ganga Dussehra | Dasahara, Ganga Avataran, Ganga Dashahara, Gangavatar |
| 107 | Ganga Sagar Mela | - |
| 108 | Ganga Saptami | Gangotpatte, Gangotpatti |
| 109 | Gauri Vrat (Molakat) Begins | - |
| 110 | Gayatri Japam | - |
| 111 | Goga Navami | Gugga Naumi, Shri Goga Navami |
| 112 | Goga Pancham | Goga Panchami (Nag Panchami - Gujarat) |
| 113 | Gopashtami | - |
| 114 | Govardhan Puja | Annakut, Govardhan Utsav |
| 115 | Gowri Habba (Swarna Gauri Vrata) | - |
| 116 | Gunatitanand Swami Diksha Day | - |
| 117 | Gunatitanand Swami Jayanti | - |
| 118 | Guru Nanak Jayanti (Kartika Purnima) | - |
| 119 | Hanuman Puja | Deepavali Hanuman Puja, Kali Chaudas |
| 120 | Hariyali Teej | - |
| 121 | Hartalika Teej | Kevada Trij |
| 122 | Hindola Festival Begins | - |
| 123 | Hindola Festival Ends | - |
| 124 | Holashtak Prarambh | Holi Ashtak Begins |
| 125 | Holashtak Samapt | Holi Ashtak Ends |
| 126 | Holika Dahan | - |
| 127 | Indra Savarni Manvadi | - |
| 128 | Ishti | - |
| 129 | Jagaddhatri Puja | - |
| 130 | Jagannath Ratha Yatra | - |
| 131 | Jagannath Rath Yatra | - |
| 132 | Jalaram Jayanti | - |
| 133 | Jamai Shashti | - |
| 134 | Janaki Jayanti | Sita Ashtami |
| 135 | Jaya Parvati Vrat Begins | - |
| 136 | Jur Sital | - |
| 137 | Jyeshtha Adhika Purnima | - |
| 138 | Jyeshtha Amavasya | - |
| 139 | Kachchhi Halari Ashadhi Varsharambh | Ashadhi Beej Varsharambh, Halari Nutan Varsh, Kachchhi Nutan Varsh |
| 140 | Kajari Teej | - |
| 141 | Kalabhairav Jayanti | - |
| 142 | Kali Chaudas (Naraka Chaturdashi) | Deepavali Hanuman Puja, Hanuman Puja, Kali Chaudas |
| 143 | Kali Puja | Diwali, Kali Puja (Shyama Puja) |
| 144 | Kali Yuga Diwas | - |
| 145 | Kalparambha | - |
| 146 | Kanya Sankranti (Vishwakarma Puja) | - |
| 147 | Karadayan Nombu | - |
| 148 | Karam Puja | - |
| 149 | Karka Sankranti | - |
| 150 | Karthigai Deepam | - |
| 151 | Kartika Amavasya | - |
| 152 | Kartika Snan Prarambh | Kartik Snan Begins |
| 153 | Kartika Snan Samapt | Kartik Snan Ends |
| 154 | Karwa Chauth | Karak Chaturthi |
| 155 | Kati Bihu (Kongali Bihu) | Kongali Bihu |
| 156 | Kedar Gauri Vrat | - |
| 157 | Kojagari Lakshmi Puja | Kojagara Lakshmi Puja, Sharad Purnima |
| 158 | Krishna Bhishma Dwadashi | - |
| 159 | Krishna Damodara Dwadashi | - |
| 160 | Krishna Kalki Dwadashi | - |
| 161 | Krishna Kurma Dwadashi | - |
| 162 | Krishna Matsya Dwadashi | - |
| 163 | Krishna Narasimha Dwadashi | - |
| 164 | Krishna Padmanabha Dwadashi | - |
| 165 | Krishna Parashurama Dwadashi | - |
| 166 | Krishna Ramalakshmana Dwadashi | - |
| 167 | Krishna Vamana Dwadashi | - |
| 168 | Krishna Vasudeva Dwadashi | - |
| 169 | Krishna Yogeshwara Dwadashi | - |
| 170 | Kubjika Jayanti | - |
| 171 | Kumbha Sankranti | - |
| 172 | Kurma Dwadashi | - |
| 173 | Kurma Jayanti | Kurma Jayanti (Swaminarayan/Satsangi), Swaminarayan Kurma Jayanti |
| 174 | Kushotpatini Amavasya | - |
| 175 | Labh Chaturthi | - |
| 176 | Labh Panchami | Labh Pancham, Saubhagya Panchami |
| 177 | Lakshmi Panchami | - |
| 178 | Lakshmi Puja (Deepavali) | Deepavali, Dipotsav, Diwali, Diwali Lakshmi Puja, Lakshmi Puja |
| 179 | Lalita Panchami | - |
| 180 | Lohri | - |
| 181 | Losar | - |
| 182 | Magha Amavasya | Mauni Amavasya |
| 183 | Magha Gupt Navaratri Day 1 (Ghatasthapana) | - |
| 184 | Magha Gupt Navaratri Day 2 | - |
| 185 | Magha Gupt Navaratri Day 3 | - |
| 186 | Magha Gupt Navaratri Day 4 | - |
| 187 | Magha Gupt Navaratri Day 5 | - |
| 188 | Magha Gupt Navaratri Day 6 | - |
| 189 | Magha Gupt Navaratri Day 7 | - |
| 190 | Magha Gupt Navaratri Day 8 | - |
| 191 | Magha Gupt Navaratri Day 9 | - |
| 192 | Magha Gupt Navaratri Parana (Dashami) | - |
| 193 | Magha Snan Prarambh | Magha Snan Begins |
| 194 | Magha Snan Samapt | Magha Snan Ends |
| 195 | Magh Bihu | Bhogali Bihu, Magh Bihu (Bhogali Bihu) |
| 196 | Maha Bharani | - |
| 197 | Mahant Swami Maharaj Janma Jayanti | - |
| 198 | Mahant Swami Maharaj Parshadi Diksha Din (Official Jayanti) | - |
| 199 | Maha Saptami (Durga Puja) | - |
| 200 | Mahavir Jayanti | - |
| 201 | Mahesh Navami | - |
| 202 | Makara Sankranti (Pongal) | Ghughuti, Khichdi, Maghi, Makar Puja, Pongal, Sakraat, Til Sankranti, Uttarayan |
| 203 | Makaravilakku | - |
| 204 | Mandala Pooja | - |
| 205 | Mandala Pooja Begins | - |
| 206 | Margashirsha Amavasya | - |
| 207 | Matangi Jayanti | - |
| 208 | Matsya Dwadashi | - |
| 209 | Mattu Pongal | - |
| 210 | Meena Sankranti | - |
| 211 | Meerabai Jayanti | - |
| 212 | Mesha Sankranti | Baisakhi, Mesha Vishu, Puthandu |
| 213 | Mithuna Sankranti | - |
| 214 | Mota Yaksh Fair (Jakh Bahotera) | - |
| 215 | Mota Yaksh Fair Day 2 | - |
| 216 | Mota Yaksh Fair Day 3 | - |
| 217 | Mukutotsav Purnima | Mukutotsav Poonam |
| 218 | Nabanna Utsav | - |
| 219 | Naga Panchami (Telugu) | - |
| 220 | Nag Panchami | Nag Pancham |
| 221 | Nagula Chavithi | - |
| 222 | Nand Mahotsav | Nanda Mahotsav |
| 223 | Nara-Narayan Arjun Janmotsav | Arjun Janmotsav, Nara-Narayan Janmotsav |
| 224 | Naraka Chaturdashi Abhyanga Snan | Abhyanga Snan, Narak Chaturdashi |
| 225 | Narasimha Dwadashi | - |
| 226 | Narmada Jayanti | - |
| 227 | Narsinh Mehta Janma Jayanti | - |
| 228 | Navpatrika Puja | - |
| 229 | Nuakhai | - |
| 230 | Onam (Thiruvonam) | - |
| 231 | Pana Sankranti | Maha Vishuba Sankranti |
| 232 | Panguni Uthiram | - |
| 233 | Parashara Rishi Jayanti | - |
| 234 | Parashurama Jayanti | Parashuram Jayanti, Parashurama Jayanti (Pradosha Tradition), Parashurama Jayanti (Swaminarayan/Satsangi) |
| 235 | Pausha Purnima | Poshi Poonam, Poshi Purnima, Shakambhari Jayanti, Shakambhari Purnima |
| 236 | Pavitra Festival | Pavitra Arpan, Pavitra Arpan Utsav |
| 237 | Phalguna Amavasya | - |
| 238 | Phuldolotsava | Fuldol Utsav, Phalgun Dolotsav, Phooldolotsav, Pushpadolotsav |
| 239 | Phulera Dooj | - |
| 240 | Pitru Paksha Begins | - |
| 241 | Pohela Boishakh | Pahela Baishakh |
| 242 | Pola | - |
| 243 | Pradyumna Chaturthi | - |
| 244 | Pramukh Swami Maharaj Jayanti | - |
| 245 | Pramukh Varni Din | - |
| 246 | Purnima Shraddha | - |
| 247 | Radha Ashtami | Radhashtami |
| 248 | Raivata Manvadi | - |
| 249 | Raja Parba Day 1 | - |
| 250 | Raja Parba Day 2 | - |
| 251 | Raja Parba Day 3 | - |
| 252 | Ramakrishna Jayanti | - |
| 253 | Ramanuja Jayanti | - |
| 254 | Randhan Chhath | - |
| 255 | Rang Panchami | Dev Holi, Dev Panchami, Ranga Panchami, Rangpanchami |
| 256 | Ratha Saptami | Ratha Saptami (Surya Jayanti) |
| 257 | Ravechi Mata Fair | - |
| 258 | Rigveda Upakarma | - |
| 259 | Rishi Panchami | - |
| 260 | Rongali Bihu Day 1 | Bohag Bihu, Bohag Bihu Day 1, Goru Bihu |
| 261 | Rongali Bihu Day 2 | Bohag Bihu Day 2, Manuh Bihu |
| 262 | Rongali Bihu Day 3 | Bohag Bihu Day 3, Gosai Bihu |
| 263 | Rongali Bihu Day 4 | Bohag Bihu Day 4, Kutum Bihu |
| 264 | Rongali Bihu Day 5 | Bohag Bihu Day 5, Senehi Bihu |
| 265 | Rongali Bihu Day 6 | Bohag Bihu Day 6, Mela Bihu |
| 266 | Rongali Bihu Day 7 | Bohag Bihu Day 7, Chera Bihu |
| 267 | Rudra Savarni Manvadi | - |
| 268 | Sajaibu Cheiraoba | - |
| 269 | Samaveda Upakarma | - |
| 270 | Sandhi Puja | - |
| 271 | Sankarshana Chaturthi | - |
| 272 | Saraswati Avahan | - |
| 273 | Saraswati Balidan | - |
| 274 | Saraswati Visarjan | - |
| 275 | Sata Yuga Diwas | Akshaya Navami, Kushmanda Navami |
| 276 | Savarni Manvadi | - |
| 277 | Shabari Jayanti | - |
| 278 | Shastriji Maharaj Jayanti | - |
| 279 | Sheetala Ashtami | Basoda, Sheetala Aatham |
| 280 | Shravana Amavasya | Aadi Amavasai, Hariyali Amavasya, Pithori Amavasya |
| 281 | Shravana Maas Begins | Shiva Puja Begins, Shravan Maas Begins, Shravan Shivpujan, Shravana Masarambh |
| 282 | Shree Hari Antardhan | Antardhan Leela, Hari Antardhan, Hari Tirodhan, Shree Hari Tirodhan, Swadhaam Gaman, Swadham Gaman |
| 283 | Siddhilakshmi Jayanti | - |
| 284 | Simha Sankranti | - |
| 285 | Sita Navami | - |
| 286 | Skanda Sashti | Kanda Sashti (Soorasamharam), Skanda Shashti Vratam |
| 287 | Snanyatra | - |
| 288 | Subrahmanya Shashti (Champa Shashthi) | Champa Shashthi |
| 289 | Surdas Jayanti | - |
| 290 | Swaminarayan Rathyatra | - |
| 291 | Swarochisha Manvadi | - |
| 292 | Swayambhuva Manvadi | - |
| 293 | Tamasa Manvadi | - |
| 294 | Tara Jayanti | - |
| 295 | Tarnetar Fair | - |
| 296 | Tarnetar Fair Day 2 | - |
| 297 | Tarnetar Fair Day 3 | - |
| 298 | Telugu Hanuman Jayanti | Telugu Hanuman Jayanthi, Telugu Hanuman Vratam |
| 299 | Thai Amavasai | Thai Amavasya |
| 300 | Thai Poosam | - |
| 301 | Thrissur Pooram | - |
| 302 | Treta Yuga Diwas | Akshaya Tritiya |
| 303 | Tula Sankranti | - |
| 304 | Tulsidas Jayanti | - |
| 305 | Tulsi Vivah | - |
| 306 | Ugadi | Chaitra Samvatsara Prarambh, Gudi Padwa, Samvatsara Prarambha |
| 307 | Uttama Manvadi | - |
| 308 | Vachanamrut Jayanti | - |
| 309 | Vagh Baras | Bachha Baras, Govatsa Dwadashi, Vasu Baras |
| 310 | Vaishakha Amavasya | Shani Jayanti, Vat Savitri Vrat |
| 311 | Vaishakh Snan Prarambh | Chaitra Purnima Snan Start, Vaishakh Snan Begins |
| 312 | Vaishakh Snan Samapt | Vaishakh Purnima Snan Samapt, Vaishakh Snan Ends |
| 313 | Vaivaswata Manvadi | - |
| 314 | Vallabhacharya Jayanti | - |
| 315 | Valmiki Jayanti | - |
| 316 | Vamana Dwadashi | - |
| 317 | Vamana Jayanti | - |
| 318 | Varada Chaturthi | - |
| 319 | Varaha Dwadashi | - |
| 320 | Vasant Panchami | Saraswati Jayanti, Saraswati Puja, Shikshapatri Jayanti, Shree Panchami, Vasant Panchami (Saraswati Puja) |
| 321 | Vasi Uttarayan | - |
| 322 | Vasudeva Chaturthi | - |
| 323 | Vidyarambham | Vidyarambham Day |
| 324 | Vinayaka Chaturthi | Ganesh Chaturthi, Siddhivinayaka Chaturthi |
| 325 | Vishu | - |
| 326 | Vishwakarma Jayanti | - |
| 327 | Vivah Panchami | - |
| 328 | Vivekananda Jayanti (Samvat) | - |
| 329 | Vrischika Sankranti | - |
| 330 | Vrishabha Sankranti | - |
| 331 | Yajurveda Upakarma | - |
| 332 | Yama Deepam | - |
| 333 | Yama Panchaka Begins | - |
| 334 | Yaoshang | - |
| 335 | Yashoda Jayanti | - |
| 336 | Yogi Maharaj Jayanti | - |

## Vrat Identities (Catalog: 124)

| # | Identity | Alias(es) |
|---:|---|---|
| 1 | Adhika Purnima Vrat | - |
| 2 | Ahoi Ashtami | - |
| 3 | Aja Ekadashi | Annada Ekadashi, Kaliya Dalana Ekadashi |
| 4 | Akhand Dwadashi | Agahan Akhand Dwadashi, Akhand Dwadashi Vrat, Akhanda Dwadashi, Magshar Akhand Dwadashi, Margashirsha Akhand Dwadashi |
| 5 | Akhuratha Sankashti Chaturthi | Akhuratha Sankashti, Sankashti Chaturthi |
| 6 | Amalaki Ekadashi | Amla Ekadashi, Rangbhari Ekadashi |
| 7 | Apara Ekadashi | Achala Ekadashi |
| 8 | Ashadha Purnima Vrat | - |
| 9 | Ashoka Ashtami Vrat | - |
| 10 | Ashvina Purnima | Ashwina Purnima, Ashwina Purnima Vrat |
| 11 | Balarama Jayanti | Baladeva Chhath, Balarama Jayanti (Hala Shashthi), Balbhadra Jayanti, Baldev Chhath, Hal Shashthi (Balarama Jayanti) |
| 12 | Bhadrapada Purnima | Bhadrapada Purnima Vrat |
| 13 | Bhalachandra Sankashti Chaturthi | Bhalachandra Sankashti, Sankashti Chaturthi |
| 14 | Bhanu Saptami | - |
| 15 | Bhauma Pradosh Vrat | Pradosh Vrat |
| 16 | Budha Pradosh Vrat | Pradosh Vrat |
| 17 | Budhwar Vrat | Wednesday Vrat |
| 18 | Chaitra Purnima Vrat | - |
| 19 | Chaitri Dolotsav | Chaitra Sud 11 Vishnu Dolotsav, Chaitri Hindola, Vimala Ekadashi, Vimala Ekadashi Dolotsav |
| 20 | Chandra Darshana | - |
| 21 | Chandrayan Vrat | - |
| 22 | Chaturmasa Begins | Chaturmas Prarambh, Devashayana Kala Begins |
| 23 | Chaturmasa Ends | Chaturmas Samapt, Devashayana Kala Ends |
| 24 | Chhath Puja (Sandhya Arghya) | Chhath Puja (Surya Shashthi) |
| 25 | Dahi Vrata Begins | - |
| 26 | Darsha Amavasya | - |
| 27 | Devshayani Ekadashi | Ashadhi Ekadashi, Devpodhi Ekadashi, Harishayani Ekadashi, Prathama Ekadashi, Shayani Ekadashi, Toli Ekadashi |
| 28 | Devutthana (Prabodhini) Ekadashi | Dev Uthani Ekadashi, Devuthi Ekadashi, Devutthana Ekadashi, Gauna Devutthana Ekadashi, Haribodhini Ekadashi, Kartiki Ekadashi, Papaharini Ekadashi, Prabodhini Ekadashi, Uttana Ekadashi, Vaishnava Devutthana Ekadashi, Vishnu Prabodhini Ekadashi |
| 29 | Dharmadev Janmotsav | - |
| 30 | Dudh Vrata Begins | - |
| 31 | Dwidala Vrata Begins | Dwidal Vrata Begins |
| 32 | Dwijapriya Sankashti Chaturthi | Dwijapriya Sankashti, Sankashti Chaturthi |
| 33 | Ekadanta Sankashti Chaturthi | Ekadanta Sankashti, Sankashti Chaturthi |
| 34 | First Mangala Gauri Vrat | - |
| 35 | Fourth Mangala Gauri Vrat | - |
| 36 | Gajanana Sankashti Chaturthi | Gajanana Sankashti, Sankashti Chaturthi |
| 37 | Ganadhipa Sankashti Chaturthi | Ganadhipa Sankashti, Sankashti Chaturthi |
| 38 | Gangaur | Gauri Teej, Gauri Tritiya, Saubhagya Gauri Tritiya |
| 39 | Guru Pradosh Vrat | Pradosh Vrat |
| 40 | Guruvar Vrat | Brihaspativar Vrat, Thursday Vrat |
| 41 | Hari Jayanti | Shree Hari Jayanti, Shri Hari Jayanti, Shree Hari Navmi |
| 42 | Hatadi Festival | - |
| 43 | Heramba Sankashti Chaturthi | Heramba Sankashti, Sankashti Chaturthi |
| 44 | Indira Ekadashi | Ekadashi Shradh, Pitri Uddhar Ekadashi |
| 45 | Jaya Ekadashi | Bhaimi Ekadashi, Bhishma Ekadashi, Gauna Jaya Ekadashi, Vaishnava Jaya Ekadashi |
| 46 | Jivitputrika Vrat (Jitiya) | - |
| 47 | Jyeshtha Purnima | Vat Purnima, Vat Savitri Purnima |
| 48 | Kalashtami | - |
| 49 | Kalki Jayanti | - |
| 50 | Kamada Ekadashi | Kamana Ekadashi, Phalda Ekadashi |
| 51 | Kamika Ekadashi | - |
| 52 | Kannada Hanuman Vratam | - |
| 53 | Kartika Purnima | Dev Deepavali, Kartika Purnima Vrat, Tripuri Purnima |
| 54 | Karva Chauth | - |
| 55 | Krishna Janmashtami | Gokulashtami, Krishna Janmashtami (Smarta), Krishna Janmashtami (Swaminarayan-Uddhav) |
| 56 | Krishnapingala Sankashti Chaturthi | Krishnapingala Sankashti, Sankashti Chaturthi |
| 57 | Kurma Jayanti (Vaishakha Purnima Tradition) | Kurma Avatara Appearance, Kurma Jayanti, Shri Koorma Jayanti |
| 58 | Lambodara Sankashti Chaturthi | Lambodara Sankashti, Sankashti Chaturthi |
| 59 | Maghi Purnima | Guru Ravidas Jayanti, Lalita Jayanti, Magha Purnima, Magha Purnima Vrat |
| 60 | Maha Sangada Hara Chathurti | - |
| 61 | Maha Shivaratri | - |
| 62 | Mangalwar Vrat | Tuesday Vrat |
| 63 | Margashirsha Purnima Vrat | Margashirsha Purnima |
| 64 | Masik Durgashtami | - |
| 65 | Masik Karthigai | - |
| 66 | Masik Krishna Janmashtami | - |
| 67 | Masik Shivaratri | - |
| 68 | Matsya Jayanti | - |
| 69 | Mohini Ekadashi | Laxmi Narayan Ekadashi |
| 70 | Mokshada Ekadashi (Geeta Jayanti) | Geeta Jayanti, Gita Jayanti Ekadashi, Mauna Ekadashi, Mokshada Ekadashi |
| 71 | Narasimha Jayanti | - |
| 72 | Nirjala Ekadashi | Bhima Ekadashi, Bhimseni Ekadashi, Pandava Ekadashi |
| 73 | Padmini Ekadashi | Kamala Ekadashi, Padmini Vishuddha Ekadashi, Purushottami Ekadashi |
| 74 | Pandharpur Yatra | Ashadhi Ekadashi Yatra, Ashadhi Wari, Pandharpur Wari |
| 75 | Papankusha Ekadashi | - |
| 76 | Papmochani Ekadashi | Papavimocani Ekadashi |
| 77 | Parama Ekadashi | Parama Shuddha Ekadashi |
| 78 | Parivartini Ekadashi | Danleela Mahotsav, Dol Gyaras, Jal Jhilani Ekadashi, Jayanti Ekadashi, Padma Ekadashi, Parshva Ekadashi, Parsva Ekadashi, Vamana Ekadashi |
| 79 | Pausha Purnima Vrat | Paush Purnima Vrat |
| 80 | Pausha Putrada Ekadashi | - |
| 81 | Phalguna Purnima | Chaitanya Mahaprabhu Jayanti, Dol Purnima, Gaura Purnima, Lakshmi Jayanti, Phalguna Purnima Vrat, Vasanta Purnima |
| 82 | Rama Ekadashi | Rambha Ekadashi, Rameshwaram Ekadashi |
| 83 | Ramanand Swami Appearance Festival | Ramanand Swami Pradurbhavotsav |
| 84 | Rama Navami | Rama Navami (Smarta), Rama Navami (Vaishnava) |
| 85 | Ravi Pradosh Vrat | Pradosh Vrat |
| 86 | Ravivar Vrat | Navagraha Weekday Fasting, Sunday Vrat |
| 87 | Rohini Vrat | - |
| 88 | Sankashti Chaturthi | Angarak Sankashti Chaturthi, Angarki Sankashti Chaturthi |
| 89 | Saphala Ekadashi | - |
| 90 | Second Mangala Gauri Vrat | - |
| 91 | Shaka Vrata Begins | - |
| 92 | Shani Pradosh Vrat | Pradosh Vrat |
| 93 | Shanivar Vrat | Saturday Vrat |
| 94 | Shattila Ekadashi | Tila Ekadashi, Tilda Ekadashi |
| 95 | Sheetala Satam | Sheetala Saptami |
| 96 | Shravana Purnima | Gayatri Jayanti, Hayagriva Jayanti, Narali Purnima, Raksha Bandhan, Rakshabandh, Rakshabandhan, Shravana Purnima Vrat |
| 97 | Shravana Putrada Ekadashi | Pavitra Ekadashi, Pavitra Utsav, Pavitran Ekadashi, Pavitropana Ekadashi, Pavitrotsava, Vaishnava Shravana Putrada Ekadashi |
| 98 | Shravana Somvar (Monday Fasting) | - |
| 99 | Shri Satyanarayana Vrat | - |
| 100 | Shukra Pradosh Vrat | Pradosh Vrat |
| 101 | Shukravar Vrat | Friday Vrat |
| 102 | Soma Pradosh Vrat | Pradosh Vrat |
| 103 | Somwar Vrat | Deities Weekdays Fasting, Monday Vrat |
| 104 | Swaminarayan Jayanti (Hari-Nom) | - |
| 105 | Swaminarayan Varaha Jayanti | Shree Varaha Jayanti |
| 106 | Tamil Hanumath Jayanthi | - |
| 107 | Thai Pusam | - |
| 108 | Third Mangala Gauri Vrat | - |
| 109 | Utpanna Ekadashi | Utpatti Ekadashi |
| 110 | Vaikasi Visakam | - |
| 111 | Vaikuntha Chaturdashi | - |
| 112 | Vaishakha Purnima | Buddha Purnima, Chitra Pournami, Vaishakha Purnima Vrat |
| 113 | Vakratunda Sankashti Chaturthi | Sankashti Chaturthi, Vakratunda Sankashti |
| 114 | Varaha Jayanti | - |
| 115 | Varalakshmi Vratam | - |
| 116 | Varuthini Ekadashi | Baruthani Ekadashi |
| 117 | Vibhuvana Sankashti Chaturthi | Sankashti Chaturthi, Vibhuvana Sankashti |
| 118 | Vighnaraja Sankashti Chaturthi | Sankashti Chaturthi, Vighnaraja Sankashti |
| 119 | Vijaya Ekadashi | - |
| 120 | Vikata Sankashti Chaturthi | Sankashti Chaturthi, Vikata Sankashti |
| 121 | Vinayaki Chaturthi | - |
| 122 | Vratni Purnima | - |
| 123 | Yamuna Chhath | - |
| 124 | Yogini Ekadashi | Anasara Ekadashi, Khalilagi Ekadashi |
