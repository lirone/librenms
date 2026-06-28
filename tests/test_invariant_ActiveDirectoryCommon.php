<?php

use PHPUnit\Framework\TestCase;
use LibreNMS\Authentication\ActiveDirectoryCommon;

class ActiveDirectoryCommonSecurityTest extends TestCase
{
    /**
     * Invariant: LDAP filter methods must escape user input to prevent injection
     */
    public function testUserFilterEscapesLdapInjection()
    {
        $adAuth = $this->getMockBuilder(ActiveDirectoryCommon::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMockForAbstractClass();

        $payloads = [
            // Exact exploit case - LDAP injection payload
            'admin)(objectClass=*))(|(samaccountname=*',
            // Boundary case - special LDAP characters
            '*)(&',
            // Valid input - normal username
            'testuser',
            // Additional injection attempt
            ')(|(userpassword=*',
            // Empty string edge case
            '',
        ];

        foreach ($payloads as $username) {
            $result = $adAuth->userFilter($username);
            
            // The security property: LDAP metacharacters must be escaped
            // If properly escaped, the parentheses should be balanced and
            // the username should appear literally in the filter
            $this->assertStringContainsString(
                $this->escapeLdapFilter($username),
                $result,
                "Username '$username' not properly escaped in LDAP filter"
            );
            
            // Additional check: filter should be syntactically valid
            // Count opening and closing parentheses
            $open = substr_count($result, '(');
            $close = substr_count($result, ')');
            $this->assertEquals(
                $open,
                $close,
                "LDAP filter has unbalanced parentheses for username '$username'"
            );
        }
    }

    /**
     * Helper to demonstrate proper LDAP escaping
     * This shows what the production code SHOULD do
     */
    private function escapeLdapFilter(string $input): string
    {
        // Proper LDAP filter escaping according to RFC 4515
        $escapeMap = [
            '\\' => '\\5c',
            '*'  => '\\2a',
            '('  => '\\28',
            ')'  => '\\29',
            "\0" => '\\00',
        ];
        
        return str_replace(
            array_keys($escapeMap),
            array_values($escapeMap),
            $input
        );
    }
}