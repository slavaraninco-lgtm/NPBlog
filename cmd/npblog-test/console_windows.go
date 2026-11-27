//go:build windows

package main

import (
	"bufio"
	"fmt"
	"os"
	"unsafe"

	"golang.org/x/sys/windows"
)

// maybePauseOnExplorerLaunch waits for Enter only when launched standalone via Windows Explorer double-click.
func maybePauseOnExplorerLaunch(noPause bool) {
	if noPause {
		return
	}
	kernel32 := windows.NewLazySystemDLL("kernel32.dll")
	proc := kernel32.NewProc("GetConsoleProcessList")
	if proc.Find() != nil {
		return
	}
	var pids [4]uint32
	ret, _, _ := proc.Call(uintptr(unsafe.Pointer(&pids[0])), uintptr(len(pids)))
	if ret == 1 {
		fmt.Print("\nНажмите Enter для выхода...")
		_, _ = bufio.NewReader(os.Stdin).ReadString('\n')
	}
}
