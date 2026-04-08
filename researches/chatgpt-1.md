I reviewed your uploaded `FESTIVALS` array and checked it against authoritative general-Hindu sources plus official Swaminarayan/BAPS sources. Bottom line: it is a strong broad list, but it is **not actually complete**, and a few entries are **misleading because they merge distinct festivals**. The file itself presents the list as a “Complete list of Hindu festivals” and includes merged labels such as `Makara Sankranti (Pongal]`, `Skanda Shashti / Chhath Puja`, and `Vasant Panchami / Shikshapatri Jayanti`.   

The clearest correction is `Makara Sankranti (Pongal]`. These are related, but not strict synonyms. Makar Sankranti is the pan-Indian solar festival marking the Sun’s entry into Makara, while Pongal is a **four-day Tamil harvest festival** tied to the same seasonal/solar moment. Better data modeling would keep them separate and link them as related observances.  ([Encyclopedia Britannica][1])

The other strong correction is `Skanda Shashti / Chhath Puja`. These should not be one combined festival entry. Chhath Puja is a **four-day solar festival** centered on Surya and Chhathi Maiya, especially in Bihar, eastern Uttar Pradesh, Jharkhand, and Nepal, while Britannica explicitly notes that Skandashashthi and Chhath Puja are **distinct calendrical observances**.  ([Encyclopedia Britannica][2])

`Mesha Sankranti (Baisakhi / Puthandu]` is not wrong, but it is **under-labelled** for a list claiming regional completeness. Official Indian and Hindu community sources treat this solar new-year cluster as including names such as **Vaisakhi, Vishu, Bohag Bihu, Poila Boishakh, Meshadi, Vaishakhadi, and Puthandu**, not just Baisakhi/Puthandu. So this entry should either gain a richer alias set or be broken into umbrella-plus-regional observances.  ([President of India][3])

There is also a non-doctrinal but important **data-quality problem**: many titles and descriptions use `]` where a closing `)` is clearly intended, for example `Makara Sankranti (Pongal]`, `Chaitra (Vasant] Navaratri...`, `Hari-Nom]`, `Prabodhini]`, and similar patterns throughout the file. That is not theological misinformation, but it will make the dataset look unreliable and will hurt downstream display/search quality.  

The strongest missing Hindu festivals I found are **Vishu** and **Onam**. Vishu is a recognized Hindu festival of Kerala/adjacent Tamil areas, and Onam is a major Hindu harvest festival in Kerala; neither appears in your uploaded list despite the file’s “complete list” claim.   ([Encyclopedia Britannica][4])

Within the Swaminarayan/BAPS portion, the two clearest omissions are **Shastriji Maharaj Jayanti** and **Pramukh Varni Din**. Official BAPS sources state that Vasant Panchami is also the birth anniversary of **Shastriji Maharaj**, but your file only labels that entry as `Vasant Panchami / Shikshapatri Jayanti`. Official BAPS festival listings also include **Pramukh Varni Din**, which is absent from your file.  ([BAPS Swaminarayan Sanstha][5])

A smaller but still real Swaminarayan/BAPS omission is **Rang Panchami**. Official BAPS annual festival listings include it, but it does not appear in your uploaded festival list.   ([BAPS Swaminarayan Sanstha][6])

So my audit verdict is:

* **Definitely misleading / should be split:** `Makara Sankranti (Pongal]`, `Skanda Shashti / Chhath Puja`.   ([Encyclopedia Britannica][1])
* **Not complete enough for the claim “complete list”:** missing at least `Vishu`, `Onam`, `Shastriji Maharaj Jayanti`, `Pramukh Varni Din`, and `Rang Panchami`.   ([Encyclopedia Britannica][4])
* **Needs naming cleanup:** Mesha Sankranti alias coverage is too narrow, and the file has widespread bracket/typo problems.  ([President of India][3])

Overall: **good foundation, not complete, and a few merged entries should be corrected before calling it authoritative.** Next step could be a cleaned, corrected festival map in the same code format, with `aliases`, `umbrella_festival`, and `related_observance` fields so regional variants and sect observances are represented cleanly.

[1]: https://www.britannica.com/topic/Pongal?utm_source=chatgpt.com "Pongal | Harvest Festival, Rituals, Tamil Nadu, India, & Rice | Britannica"
[2]: https://www.britannica.com/topic/Chhath-Puja?utm_source=chatgpt.com "Chhath Puja | History, Rituals, Key Deities, Celebrations, Bihar, & India | Britannica"
[3]: https://presidentofindia.nic.in/press_releases/presidents-greetings-eve-vaisakhi-vishu-bohag-bihu-poila-boishakh-meshadi?utm_source=chatgpt.com "PRESIDENT’S GREETINGS ON THE EVE OF VAISAKHI, VISHU, BOHAG BIHU, POILA BOISHAKH, MESHADI, VAISHAKHADI AND PUTHANDU PIRAPU | President of India"
[4]: https://www.britannica.com/topic/Vishu?utm_source=chatgpt.com "Vishu | Festival, Decoration, Items, & Significance | Britannica"
[5]: https://www.baps.org/cultureandheritage/Traditions/AnnualCelebrationsandFestivals/VasantPanchmi.aspx?utm_source=chatgpt.com "Vasant Panchami"
[6]: https://www.baps.org/Calendar/2026/FestivalList.aspx?utm_source=chatgpt.com "FestivalList 2026"
