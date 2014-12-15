<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
		version="1.0">

<!-- format literal text (`literal`) using bold text in man -->
<xsl:template match="literal">
	<xsl:text>\fB</xsl:text>
	<xsl:apply-templates/>
	<xsl:text>\fR</xsl:text>
</xsl:template>

</xsl:stylesheet>
