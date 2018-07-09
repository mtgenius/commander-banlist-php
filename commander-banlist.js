var expando = function(e) {
		e.preventDefault();
		if (this.lastChild.nodeName.toLowerCase() == "div")
			this.removeChild(this.lastChild);
		else {
			var div = document.createElement("div"),
				img = document.createElement("img"),
				txt = this.innerText.replace(/\:$/, "");
			img.setAttribute("alt", txt);
			img.setAttribute("height", "310");
			img.setAttribute("src", "//i.mtgeni.us/cards/" + this.dataset.multiverseid + ".jpg");
			img.setAttribute("title", txt);
			img.setAttribute("width", "223");
			div.appendChild(img);
			this.appendChild(div);
		}
	},
	tbodies = document.getElementsByTagName("tbody"),
	x;
for (x = 0; x < tbodies.length; x++) {
	var ths = tbodies.item(x).getElementsByTagName("th"),
		y;
	for (y = 0; y < ths.length; y++) {
		var as = ths.item(y).getElementsByTagName("a");
		if (as.length)
			as.item(0).addEventListener("click", expando);
	}
}
