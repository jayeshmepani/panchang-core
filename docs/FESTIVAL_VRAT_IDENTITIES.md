# Festival And Vrat Identities

Canonical catalog of festival and vrat identities defined by `FestivalService::FESTIVALS`. The first column is a serial number for reading convenience; **Identity** is the catalog key (and the usual `name_key` when the observance is emitted). **Alias(es)** are alternate public names from registry aliases, traditions, display names, and recent English outputs.

## Catalog totals (general — year-independent)

| Field | Source | Count |
|---|---|---:|
| `total_festivals` | Non-vrat keys with `identity_key` collapse + generic Amavasya expanded to weekday identities (Somavati / Bhaumavati / Shani) | **336** |
| `total_vrats` | Fasting keys with `identity_key` collapse + Pradosh expanded to 7 weekday identities | **126** |

These totals are what generated JSON reports in `total_festivals` / `total_vrats`. They do **not** shrink when a definition does not fire in a given year or calendar system. Dated occurrence volume remains separate (`festival_entry_count` / `vrat_entry_count`).

**Do not confuse these layers:**

| Layer | What it is | Current value |
|---|---|---:|
| **Catalog totals** | `total_festivals` / `total_vrats` in generated JSON | **336** / **126** |
| **Year-observed unique keys** | Distinct `name_key`s that fire in a given year `by_date` dump | Always ≤ catalog; varies by year |
| **Table `#` column below** | Reading serial of listed identities (1…N) | Matches catalog totals |

A given year may still emit fewer unique `name_key`s in `by_date` than the catalog because not every definition occurs every year; those rows still count toward the catalog total.

Runtime notes:

- **Amavasya** expands to genuine weekday identities on Mon/Tue/Sat only. Those forms are listed below as separate identities (**Somavati Amavasya**, **Bhaumavati Amavasya**, **Shani Amavasya**), with `Amavasya` as alias—same pattern as weekday Pradosh rows. Māsa-named Amavasya may alias to Amavasya; Darsha Amavasya is an aparahna technical subset, not always the main Amavasya civil identity.
- **Pradosh Vrat** expands to seven weekday identities (listed below as separate rows).
- **Treta Yuga Diwas** is published under **Akshaya Tritiya** (`identity_key` collapse), not as a second festival row.
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
| 3 | Adhik Masik Krishna Janmashtami | - |
| 4 | Adhika Bhanu Saptami | - |
| 5 | Adhika Chandra Darshana | - |
| 6 | Adhika Darsha Amavasya | - |
| 7 | Adhika Kalashtami | - |
| 8 | Adhika Krishna Ramalakshmana Dwadashi | - |
| 9 | Adhika Masik Durgashtami | - |
| 10 | Adhika Masik Shivaratri | - |
| 11 | Adhika Ramalakshmana Dwadashi | - |
| 12 | Adhika Skanda Sashti | - |
| 13 | Adi Shankaracharya Jayanti | - |
| 14 | Akal Bodhon | - |
| 15 | Akshaya Tritiya | Akshaya Tritiya (Lakshmi-Narayana), Treta Yuga Diwas |
| 16 | Alankar Marjanotsav | Alankar Marjan, Alankar Marjanotsava |
| 17 | Amavasya | Amas |
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
| 51 | Bali Pratipada | Annakut, Bali Puja |
| 52 | Bathukamma (Saddula) | - |
| 53 | Bestu Varas | - |
| 54 | Bhadrapada Amavasya | Kushagrahani Amavasya, Mahalaya Amavasya, Pithori Amavasya, Sarva Pitru Amavasya |
| 55 | Bhagatji Maharaj Jayanti | - |
| 56 | Bhagavat Saptah Prarambh | Bhagavat Saptaha Begins, Bhagwat Saptah Begins |
| 57 | Bhagavat Saptah Samapt | Bhagavat Saptaha Ends, Bhagwat Saptah Ends |
| 58 | Bhai Dooj | Bhai Tika, Bhau Beej, Yama Dwitiya |
| 59 | Bhaumavati Amavasya | Amavasya, Bhauma Amavasya |
| 60 | Bhishma Ashtami | - |
| 61 | Bhishma Dwadashi | - |
| 62 | Bhishma Panchak Ends | - |
| 63 | Bhogi Pandigai | - |
| 64 | Bilva Nimantran | - |
| 65 | Bol Choth | - |
| 66 | Bonalu (Ashadha Sunday) | - |
| 67 | Brahma Savarni Manvadi | - |
| 68 | Chaiti Chhath | - |
| 69 | Chaitra (Vasant) Navaratri Day 1 (Shailaputri Puja) | Chaitra Navratri Ghatasthapana |
| 70 | Chaitra (Vasant) Navaratri Day 2 (Brahmacharini Puja) | - |
| 71 | Chaitra (Vasant) Navaratri Day 3 (Chandraghanta Puja) | - |
| 72 | Chaitra (Vasant) Navaratri Day 4 (Kushmanda Puja) | - |
| 73 | Chaitra (Vasant) Navaratri Day 5 (Skandamata Puja) | - |
| 74 | Chaitra (Vasant) Navaratri Day 6 (Katyayani Puja) | - |
| 75 | Chaitra (Vasant) Navaratri Day 7 (Kalaratri Puja) | - |
| 76 | Chaitra (Vasant) Navaratri Day 8 (Mahagauri Puja) | - |
| 77 | Chaitra (Vasant) Navaratri Day 9 (Siddhidatri Puja) | - |
| 78 | Chaitra Amavasya | - |
| 79 | Chaitra Purnima | Hanuman Jayanti, Hanuman Jayanti (North Indian) |
| 80 | Chakshusha Manvadi | - |
| 81 | Chandan Yatra Begins | Chandanotsav Begins, Chandan Yatra |
| 82 | Chandika Jayanti | - |
| 83 | Chapchar Kut | - |
| 84 | Cheti Chand | - |
| 85 | Chhinnamasta Jayanti | - |
| 86 | Chitra Pournami | - |
| 87 | Chitragupta Puja | - |
| 88 | Chopda Pujan | Deepavali Puja, Shaaradaa Pujan, Sharada Puja, Sharda Puja |
| 89 | Dada Mekan Fair (Dhrang Mela) | - |
| 90 | Dadhichi Jayanti | - |
| 91 | Daiva Savarni Manvadi | - |
| 92 | Daksha Savarni Manvadi | - |
| 93 | Damodara Dwadashi | - |
| 94 | Dattatreya Jayanti | - |
| 95 | Dayanand Saraswati Jayanti | - |
| 96 | Dev Diwali (Tripurari Purnima) | - |
| 97 | Dhanteras | Dhanatrayodashi, Dhanvantari Jayanti (Dhantrayodashi) |
| 98 | Dhanu Sankranti | Dhanurmas / Early Thal, Dhanurmas Begins, Dhanurmas Festival Begins, Early Thal Begins, Thakorji Thal Vahela Begins |
| 99 | Dhuleti | Dhulandi |
| 100 | Durga Balidan | - |
| 101 | Durga Visarjan | - |
| 102 | Dussehra | Vijayadashami, Vijayadashami (Aparajita Puja) |
| 103 | Dwapara Yuga Diwas | Mauni Amavasya |
| 104 | Dyuta Krida | - |
| 105 | Ganesh Chaturthi | Siddhivinayaka Chaturthi, Vinayaka Chaturthi |
| 106 | Ganesha Jayanti | Dhundhiraja Chaturthi, Gauriganesha Chaturthi, Tila Chaturthi, Varada Chaturthi |
| 107 | Ganga Dussehra | Dasahara, Ganga Avataran, Ganga Dashahara, Gangavatar |
| 108 | Ganga Sagar Mela | - |
| 109 | Ganga Saptami | Gangotpatte, Gangotpatti |
| 110 | Gauri Vrat (Molakat) Begins | - |
| 111 | Gayatri Japam | - |
| 112 | Goga Navami | Gugga Naumi, Shri Goga Navami |
| 113 | Goga Pancham | Goga Panchami (Nag Panchami - Gujarat) |
| 114 | Gopashtami | - |
| 115 | Govardhan Puja | Annakut, Bali Puja, Govardhan Utsav |
| 116 | Gowri Habba (Swarna Gauri Vrata) | - |
| 117 | Gunatitanand Swami Diksha Day | - |
| 118 | Gunatitanand Swami Jayanti | - |
| 119 | Guru Nanak Jayanti (Kartika Purnima) | - |
| 120 | Hanuman Puja | Deepavali Hanuman Puja, Kali Chaudas |
| 121 | Hariyali Teej | - |
| 122 | Hartalika Teej | Kevada Trij |
| 123 | Hindola Festival Begins | - |
| 124 | Hindola Festival Ends | - |
| 125 | Holashtak Prarambh | Holi Ashtak Begins |
| 126 | Holashtak Samapt | Holi Ashtak Ends |
| 127 | Holika Dahan | - |
| 128 | Indra Savarni Manvadi | - |
| 129 | Ishti | - |
| 130 | Jagaddhatri Puja | - |
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
| 154 | Kasumba Chhath | Kasumbha Chhath |
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
| 182 | Magh Bihu | Bhogali Bihu, Magh Bihu (Bhogali Bihu) |
| 183 | Magha Amavasya | Mauni Amavasya |
| 184 | Magha Gupt Navaratri Day 1 (Ghatasthapana) | - |
| 185 | Magha Gupt Navaratri Day 2 | - |
| 186 | Magha Gupt Navaratri Day 3 | - |
| 187 | Magha Gupt Navaratri Day 4 | - |
| 188 | Magha Gupt Navaratri Day 5 | - |
| 189 | Magha Gupt Navaratri Day 6 | - |
| 190 | Magha Gupt Navaratri Day 7 | - |
| 191 | Magha Gupt Navaratri Day 8 | - |
| 192 | Magha Gupt Navaratri Day 9 | - |
| 193 | Magha Gupt Navaratri Parana (Dashami) | - |
| 194 | Magha Snan Prarambh | Magha Snan Begins |
| 195 | Magha Snan Samapt | Magha Snan Ends |
| 196 | Maha Bharani | - |
| 197 | Maha Saptami (Durga Puja) | - |
| 198 | Mahalaya Amavasya | Peddala Amavasya, Pitru Amavasya, Sarvapitra Moksha Amavasya, Sarva Pitru Amavasya |
| 199 | Mahant Swami Maharaj Janma Jayanti | - |
| 200 | Mahant Swami Maharaj Parshadi Diksha Din (Official Jayanti) | - |
| 201 | Mahavir Jayanti | - |
| 202 | Mahesh Navami | - |
| 203 | Makara Sankranti (Pongal) | Ghughuti, Khichdi, Maghi, Makar Puja, Pongal, Sakraat, Til Sankranti, Uttarayan |
| 204 | Makaravilakku | - |
| 205 | Mandala Pooja | - |
| 206 | Mandala Pooja Begins | - |
| 207 | Margashirsha Amavasya | - |
| 208 | Matangi Jayanti | - |
| 209 | Matsya Dwadashi | - |
| 210 | Mattu Pongal | - |
| 211 | Meena Sankranti | - |
| 212 | Meerabai Jayanti | - |
| 213 | Mesha Sankranti | Baisakhi, Mesha Vishu, Puthandu |
| 214 | Mithuna Sankranti | - |
| 215 | Mota Yaksh Fair (Jakh Bahotera) | - |
| 216 | Mota Yaksh Fair Day 2 | - |
| 217 | Mota Yaksh Fair Day 3 | - |
| 218 | Mukutotsav Purnima | Mukutotsav Poonam |
| 219 | Nabanna Utsav | - |
| 220 | Nag Panchami | Nag Pancham |
| 221 | Naga Panchami (Telugu) | - |
| 222 | Nagula Chavithi | - |
| 223 | Nand Mahotsav | Nanda Mahotsav |
| 224 | Nara-Narayan Arjun Janmotsav | Arjun Janmotsav, Nara-Narayan Janmotsav |
| 225 | Naraka Chaturdashi Abhyanga Snan | Abhyanga Snan, Narak Chaturdashi |
| 226 | Narasimha Dwadashi | - |
| 227 | Narmada Jayanti | - |
| 228 | Narsinh Mehta Janma Jayanti | - |
| 229 | Navpatrika Puja | - |
| 230 | Nuakhai | - |
| 231 | Onam (Thiruvonam) | - |
| 232 | Pana Sankranti | Maha Vishuba Sankranti |
| 233 | Panguni Uthiram | - |
| 234 | Parashara Rishi Jayanti | - |
| 235 | Parashurama Jayanti | Parashurama Jayanti (Pradosha Tradition), Parashurama Jayanti (Swaminarayan/Satsangi), Parashuram Jayanti |
| 236 | Pausha Purnima | Poshi Poonam, Poshi Purnima, Shakambhari Jayanti, Shakambhari Purnima |
| 237 | Pavitra Festival | Pavitra Arpan, Pavitra Arpan Utsav |
| 238 | Phalguna Amavasya | - |
| 239 | Phuldolotsava | Fuldol Utsav, Phalgun Dolotsav, Phooldolotsav, Pushpadolotsav |
| 240 | Phulera Dooj | - |
| 241 | Pitru Paksha Begins | - |
| 242 | Pohela Boishakh | Pahela Baishakh |
| 243 | Pola | - |
| 244 | Pradyumna Chaturthi | - |
| 245 | Pramukh Swami Maharaj Jayanti | - |
| 246 | Pramukh Varni Din | - |
| 247 | Purnima Shraddha | - |
| 248 | Radha Ashtami | Radhashtami |
| 249 | Raivata Manvadi | - |
| 250 | Raja Parba Day 1 | - |
| 251 | Raja Parba Day 2 | - |
| 252 | Raja Parba Day 3 | - |
| 253 | Ramakrishna Jayanti | - |
| 254 | Ramanuja Jayanti | - |
| 255 | Randhan Chhath | - |
| 256 | Rang Panchami | Dev Holi, Dev Panchami, Ranga Panchami, Rangpanchami |
| 257 | Ratha Saptami | Ratha Saptami (Surya Jayanti) |
| 258 | Ravechi Mata Fair | - |
| 259 | Rigveda Upakarma | - |
| 260 | Rishi Panchami | - |
| 261 | Rongali Bihu Day 1 | Bohag Bihu, Bohag Bihu Day 1, Goru Bihu |
| 262 | Rongali Bihu Day 2 | Bohag Bihu Day 2, Manuh Bihu |
| 263 | Rongali Bihu Day 3 | Bohag Bihu Day 3, Gosai Bihu |
| 264 | Rongali Bihu Day 4 | Bohag Bihu Day 4, Kutum Bihu |
| 265 | Rongali Bihu Day 5 | Bohag Bihu Day 5, Senehi Bihu |
| 266 | Rongali Bihu Day 6 | Bohag Bihu Day 6, Mela Bihu |
| 267 | Rongali Bihu Day 7 | Bohag Bihu Day 7, Chera Bihu |
| 268 | Rudra Savarni Manvadi | - |
| 269 | Sajaibu Cheiraoba | - |
| 270 | Samaveda Upakarma | - |
| 271 | Sandhi Puja | - |
| 272 | Sankarshana Chaturthi | - |
| 273 | Saraswati Avahan | - |
| 274 | Saraswati Balidan | - |
| 275 | Saraswati Visarjan | - |
| 276 | Sata Yuga Diwas | Akshaya Navami, Kushmanda Navami |
| 277 | Savarni Manvadi | - |
| 278 | Shabari Jayanti | - |
| 279 | Shani Amavasya | Amavasya, Shanichari Amavasya |
| 280 | Shastriji Maharaj Jayanti | - |
| 281 | Sheetala Ashtami | Basoda, Sheetala Aatham |
| 282 | Shravana Amavasya | Aadi Amavasai, Hariyali Amavasya, Pithori Amavasya |
| 283 | Shravana Maas Begins | Shiva Puja Begins, Shravana Masarambh, Shravan Maas Begins, Shravan Shivpujan |
| 284 | Shree Hari Antardhan | Antardhan Leela, Hari Antardhan, Hari Antardhan Tithi, Hari Tirodhan, Shree Hari Antardhan Tithi, Shree Hari Tirodhan, Shri Hari Antardhan, Shri Hari Antardhan Tithi, Swadhaam Gaman, Swadham Gaman |
| 285 | Siddhilakshmi Jayanti | - |
| 286 | Simha Sankranti | - |
| 287 | Sita Navami | - |
| 288 | Skanda Sashti | Kanda Sashti (Soorasamharam), Skanda Shashti Vratam |
| 289 | Snanyatra | - |
| 290 | Somavati Amavasya | Amavasya, Somvati Amavasya |
| 291 | Subrahmanya Shashti (Champa Shashthi) | Champa Shashthi |
| 292 | Surdas Jayanti | - |
| 293 | Swaminarayan Rathyatra | - |
| 294 | Swarochisha Manvadi | - |
| 295 | Swayambhuva Manvadi | - |
| 296 | Tamasa Manvadi | - |
| 297 | Tara Jayanti | - |
| 298 | Tarnetar Fair | - |
| 299 | Tarnetar Fair Day 2 | - |
| 300 | Tarnetar Fair Day 3 | - |
| 301 | Telugu Hanuman Jayanti | Telugu Hanuman Jayanthi, Telugu Hanuman Vratam |
| 302 | Thai Amavasai | Thai Amavasya |
| 303 | Thrissur Pooram | - |
| 304 | Tula Sankranti | - |
| 305 | Tulsi Vivah | - |
| 306 | Tulsidas Jayanti | - |
| 307 | Ugadi | Chaitra Samvatsara Prarambh, Gudi Padwa, Samvatsara Prarambha |
| 308 | Uttama Manvadi | - |
| 309 | Vachanamrut Jayanti | - |
| 310 | Vagh Baras | Bachha Baras, Govatsa Dwadashi, Vasu Baras |
| 311 | Vaishakh Snan Prarambh | Chaitra Purnima Snan Start, Vaishakh Snan Begins |
| 312 | Vaishakh Snan Samapt | Vaishakh Purnima Snan Samapt, Vaishakh Snan Ends |
| 313 | Vaishakha Amavasya | Shani Jayanti, Vat Savitri Vrat |
| 314 | Vaivaswata Manvadi | - |
| 315 | Vallabhacharya Jayanti | - |
| 316 | Valmiki Jayanti | - |
| 317 | Vamana Jayanti | Vamana Dwadashi |
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

## Vrat Identities (`total_vrats`: 126)

| # | Identity | Alias(es) |
|---:|---|---|
| 1 | Adhika Purnima Vrat | - |
| 2 | Ahoi Ashtami | - |
| 3 | Aja Ekadashi | Annada Ekadashi, Kaliya Dalana Ekadashi |
| 4 | Akhand Dwadashi | Agahan Akhand Dwadashi, Akhanda Dwadashi, Akhand Dwadashi Vrat, Magshar Akhand Dwadashi, Margashirsha Akhand Dwadashi |
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
| 41 | Hari Jayanti | Shree Hari Jayanti, Shree Hari Navmi, Shri Hari Jayanti |
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
| 54 | Karva Chauth | Karak Chaturthi, Karwa Chauth |
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
| 92 | Shani Jayanti | Shani Dev Jayanti |
| 93 | Shani Pradosh Vrat | Pradosh Vrat |
| 94 | Shanivar Vrat | Saturday Vrat |
| 95 | Shattila Ekadashi | Tila Ekadashi, Tilda Ekadashi |
| 96 | Sheetala Satam | Sheetala Saptami |
| 97 | Shravana Purnima | Gayatri Jayanti, Hayagriva Jayanti, Narali Purnima, Rakshabandh, Raksha Bandhan, Rakshabandhan, Shravana Purnima Vrat |
| 98 | Shravana Putrada Ekadashi | Pavitra Ekadashi, Pavitran Ekadashi, Pavitra Utsav, Pavitropana Ekadashi, Pavitrotsava, Vaishnava Shravana Putrada Ekadashi |
| 99 | Shravana Somvar (Monday Fasting) | - |
| 100 | Shri Satyanarayana Vrat | - |
| 101 | Shukra Pradosh Vrat | Pradosh Vrat |
| 102 | Shukravar Vrat | Friday Vrat |
| 103 | Soma Pradosh Vrat | Pradosh Vrat |
| 104 | Somwar Vrat | Deities Weekdays Fasting, Monday Vrat |
| 105 | Swaminarayan Jayanti (Hari-Nom) | - |
| 106 | Swaminarayan Varaha Jayanti | Shree Varaha Jayanti |
| 107 | Tamil Hanumath Jayanthi | - |
| 108 | Thai Poosam | Thai Pusam, Thaipusam |
| 109 | Third Mangala Gauri Vrat | - |
| 110 | Utpanna Ekadashi | Utpatti Ekadashi |
| 111 | Vaikasi Visakam | - |
| 112 | Vaikuntha Chaturdashi | - |
| 113 | Vaishakha Purnima | Buddha Purnima, Chitra Pournami, Vaishakha Purnima Vrat |
| 114 | Vakratunda Sankashti Chaturthi | Sankashti Chaturthi, Vakratunda Sankashti |
| 115 | Varaha Jayanti | - |
| 116 | Varalakshmi Vratam | - |
| 117 | Varuthini Ekadashi | Baruthani Ekadashi |
| 118 | Vat Savitri Vrat | North Indian Vat Savitri Vrat, Vat Savitri Amavasya |
| 119 | Vibhuvana Sankashti Chaturthi | Sankashti Chaturthi, Vibhuvana Sankashti |
| 120 | Vighnaraja Sankashti Chaturthi | Sankashti Chaturthi, Vighnaraja Sankashti |
| 121 | Vijaya Ekadashi | - |
| 122 | Vikata Sankashti Chaturthi | Sankashti Chaturthi, Vikata Sankashti |
| 123 | Vinayaki Chaturthi | - |
| 124 | Vratni Purnima | - |
| 125 | Yamuna Chhath | - |
| 126 | Yogini Ekadashi | Anasara Ekadashi, Khalilagi Ekadashi |
