PULI_VERSION = "1.0.0-beta9"

BUILD_HTML_DIR = html
BUILD_MAN_DIR = man

DOC_TXT = puli.txt $(wildcard puli-*.txt)
DOC_XML = $(patsubst %.txt, %.xml, $(DOC_TXT))
DOC_HTML = $(patsubst %.txt, $(BUILD_HTML_DIR)/%.html, $(DOC_TXT))
DOC_MAN = $(patsubst %.txt, $(BUILD_MAN_DIR)/%.1, $(DOC_TXT))

ASCIIDOC = asciidoc
ASCIIDOC_HTML = xhtml11
ASCIIDOC_DOCBOOK = docbook
ASCIIDOC_COMMON = $(ASCIIDOC)
TXT_TO_HTML = $(ASCIIDOC_COMMON) -b $(ASCIIDOC_HTML)
TXT_TO_XML = $(ASCIIDOC_COMMON) -b $(ASCIIDOC_DOCBOOK)
XMLTO = xmlto
XMLTO_EXTRA = -m xsl/manpage-bold-literal.xsl
INSTALL ?= install
RM ?= rm -f
MKDIR ?= mkdir -p

# Configuration for the manpage transformation with xmlto
MANPAGE_CONFIG_XSL = xsl/manpage-config.xsl

QUIET_ASCIIDOC	= @echo "   ASCIIDOC $@";
QUIET_XMLTO	= @echo "   XMLTO $@";
QUIET_XSLTPROC = @echo "    XSLTPROC $@";

all: man html

man: $(DOC_MAN)

html: $(DOC_HTML)

# General notes on the syntax in Makefiles:
# "$@" is the name of the processed target (e.g. man/puli.1)
# "$<" is the name of the first dependency (e.g. puli.xml)

# Use AsciiDoc to transform the *.txt files to DocBook *.xml files
%.xml : %.txt
	$(QUIET_ASCIIDOC)$(RM) $@ && \
	$(TXT_TO_XML) -d manpage -o $@ $<

# Use AsciiDoc to transform the *.txt files to DocBook *.html files
$(BUILD_HTML_DIR)/%.html : %.txt
	$(QUIET_ASCIIDOC)$(RM) $@ && \
	$(MKDIR) $(BUILD_HTML_DIR) && \
	$(TXT_TO_HTML) -d manpage -o $@ $<

# Transform the DocBook *.xml files to manpages with xmlto
$(BUILD_MAN_DIR)/%.1 : %.xml $(MANPAGE_CONFIG_XSL)
	$(QUIET_XMLTO)$(RM) $@ && \
	$(MKDIR) $(BUILD_MAN_DIR) && \
	$(XMLTO) $(XMLTO_EXTRA) -o $(BUILD_MAN_DIR) -m $(MANPAGE_CONFIG_XSL) man $<

clean:
	$(RM) -r $(BUILD_HTML_DIR) $(BUILD_MAN_DIR)

.PHONY: FORCE
